"""
Blue Eco Farm — Prophet Forecasting Microservice
-------------------------------------------------
Runs as a local Flask server on port 5001.
PHP calls this via HTTP to get Prophet forecasts.

Start with:
    python prophet_service.py

Requirements:
    pip install -r requirements.txt
"""

from flask import Flask, request, jsonify
from prophet import Prophet
import pandas as pd
import logging

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "model": "prophet"})


@app.route("/forecast", methods=["POST"])
def forecast():
    """
    Expects JSON body:
    {
        "historical": [
            {"date": "2024-01-15", "qty": 120},
            ...
        ],
        "periods": 3
    }

    Returns:
    {
        "forecasts": [
            {"period": 1, "predicted_qty": 135.4, "lower": 110.2, "upper": 160.6},
            ...
        ],
        "method": "prophet"
    }
    """
    body = request.get_json(silent=True)

    if not body:
        return jsonify({"error": "Invalid JSON body"}), 400

    historical = body.get("historical", [])
    periods    = int(body.get("periods", 3))

    if len(historical) < 2:
        return jsonify({"error": "Need at least 2 historical data points for Prophet"}), 422

    if periods < 1 or periods > 24:
        return jsonify({"error": "periods must be between 1 and 24"}), 400

    try:
        # Build Prophet dataframe — requires columns 'ds' (date) and 'y' (value)
        df = pd.DataFrame(historical)
        df = df.rename(columns={"date": "ds", "qty": "y"})
        df["ds"] = pd.to_datetime(df["ds"])
        df["y"]  = pd.to_numeric(df["y"], errors="coerce").fillna(0)

        # Fit Prophet
        # - yearly_seasonality: auto-detects if enough data (>2 years)
        # - weekly_seasonality: off (we use monthly aggregates)
        # - daily_seasonality:  off
        model = Prophet(
            yearly_seasonality="auto",
            weekly_seasonality=False,
            daily_seasonality=False,
            interval_width=0.80,          # 80% confidence interval
            changepoint_prior_scale=0.05, # regularise trend changes
        )
        model.fit(df)

        # Build future dataframe — monthly frequency
        future = model.make_future_dataframe(periods=periods, freq="MS")
        forecast_df = model.predict(future)

        # Extract only the future periods (beyond the last historical date)
        last_date    = df["ds"].max()
        future_only  = forecast_df[forecast_df["ds"] > last_date].head(periods)

        forecasts = []
        for i, (_, row) in enumerate(future_only.iterrows(), start=1):
            forecasts.append({
                "period":        i,
                "predicted_qty": round(max(0.0, float(row["yhat"])),    2),
                "lower":         round(max(0.0, float(row["yhat_lower"])), 2),
                "upper":         round(max(0.0, float(row["yhat_upper"])), 2),
                "forecast_date": row["ds"].strftime("%Y-%m-%d"),
            })

        return jsonify({
            "forecasts": forecasts,
            "method":    "prophet",
        })

    except Exception as exc:
        logging.exception("Prophet forecast failed")
        return jsonify({"error": str(exc)}), 500


if __name__ == "__main__":
    # Bind to localhost only — not exposed to the internet
    app.run(host="127.0.0.1", port=5001, debug=False)
