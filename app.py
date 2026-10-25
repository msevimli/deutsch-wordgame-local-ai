import threading
from typing import List, Union

import ctranslate2
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from transformers import AutoTokenizer

MODEL_DIR = "/models/nllb-600m-ct2"

translator = ctranslate2.Translator(MODEL_DIR, device="cpu", compute_type="int8")
tokenizer = AutoTokenizer.from_pretrained(MODEL_DIR)
tok_lock = threading.Lock()  # tokenizer.src_lang shared state,

app = FastAPI(title="NLLB Translator")


class TranslateRequest(BaseModel):
    text: Union[str, List[str]]
    source: str = "eng_Latn"   # NLLB languages: tur_Latn, eng_Latn, deu_Latn ...
    target: str = "tur_Latn"
    beam_size: int = 4


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/translate")
def translate(req: TranslateRequest):
    texts = [req.text] if isinstance(req.text, str) else req.text
    if not texts:
        raise HTTPException(status_code=400, detail="text boş")

    try:
        with tok_lock:
            tokenizer.src_lang = req.source
            sources = [
                tokenizer.convert_ids_to_tokens(tokenizer.encode(t)) for t in texts
            ]

        results = translator.translate_batch(
            sources,
            target_prefix=[[req.target]] * len(sources),
            beam_size=req.beam_size,
        )

        outputs = []
        for r in results:
            tokens = r.hypotheses[0][1:]  # first token is target language, skip it
            outputs.append(
                tokenizer.decode(tokenizer.convert_tokens_to_ids(tokens))
            )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

    return {"translations": outputs}