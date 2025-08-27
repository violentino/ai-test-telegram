from fastapi import FastAPI
from pydantic import BaseModel
import base64

app = FastAPI()


class AnalyzeRequest(BaseModel):
    ticker: str
    period: str = "1y"
    interval: str = "1d"


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/analyze")
def analyze(req: AnalyzeRequest):
    # TODO: Реализовать загрузку данных, трендовые линии, уровни и сигналы
    dummy_png = base64.b64encode(b"PNG").decode()
    return {
        "image_base64": dummy_png,
        "levels": [],
        "trendlines": [],
        "signals": [],
        "meta": {
            "ticker": req.ticker,
            "period": req.period,
            "interval": req.interval,
        },
    }

