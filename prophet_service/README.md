# Prophet Forecasting Microservice

A lightweight Flask API that runs Facebook Prophet for the Blue Eco Farm demand forecasting feature.

## Setup

**1. Install Python dependencies**
```bash
cd prophet_service
pip install -r requirements.txt
```

> Prophet also requires `pystan`. If the install fails, try:
> ```bash
> pip install pystan==2.19.1.1
> pip install prophet==1.1.6
> ```

**2. Start the service**
```bash
python prophet_service.py
```

The service runs on `http://127.0.0.1:5001` (localhost only).

## How it works

- PHP (`ForecastingEngine.php`) sends historical stock data to `POST /forecast`
- Prophet fits a model and returns predictions with 80% confidence intervals
- If the service is **not running**, the system automatically falls back to the built-in hybrid statistical model (WMA + ES + OLS) — no errors shown to the user

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Check if service is running |
| POST | `/forecast` | Run a Prophet forecast |

## Forecast page indicators

- **🔮 Facebook Prophet** badge — Prophet service is running and was used
- **📐 Hybrid Statistical** badge — fallback model was used (service offline)
