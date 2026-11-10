# Rule-Based AI Assistance and Transparent Prediction

## 1. Required labels

Use these labels consistently in the interface, reports, and manuscript:

- **“Rule-Based AI-Assisted Sentiment and Theme Analysis”** — engine version `rule-v1.1`.
- **“Rule-based trend estimate”** — for `PredictionService` output.

Do **not** call the sentiment engine a trained model, LLM, neural network, or external AI service. Do **not** call prediction output “machine-learning accuracy,” “validated prediction,” or a probability.

## 2. Sentiment and theme engine

### What it is

A deterministic lexicon and clause-scoring engine in `app/Services/AIEngine.php`. It uses:

- Weighted positive and negative word lists, including selected Filipino terms.
- Positive/negative phrase lists (for example “not clear”, “masyadong mabilis”).
- Negators within the two preceding tokens.
- Intensifiers that multiply weight by 1.5.
- Clause splitting on punctuation and contrast words (`but`, `however`, `pero`, …).
- Theme keyword matching across 11 categories.

The engine is offline and reproducible. The same text and version produce the same result.

### Label rule

| Condition | Label |
|---|---|
| Positive score ≥ 0.75 **and** negative score ≥ 0.75 | `mixed` |
| Positive > 0 and positive ≥ negative | `positive` |
| Negative > 0 | `negative` |
| Positive > 0 (fallback) | `positive` |
| No lexical signal | `neutral` |

Mixed sentiment is a first-class label. A comment that praises clarity but criticizes pacing is `mixed`, not an averaged `neutral`.

### Scores

```
sentiment_score = (positive - negative) / (positive + negative)      range: -1 … +1
confidence      = min(95, 40 + min(40, total × 8) + min(15, hits × 2))
```

`confidence` is a **heuristic strength indicator**, not a calibrated probability. It rises with matched weight and keyword hits. It must not be reported as statistical confidence.

Aggregate confidence (`summarize`) is:

```
min(95, 35 + dominant_share × 40 + min(20, analyzed_comments × 1.5))
```

### Themes

Theme matching uses keyword presence per clause and assigns polarity from the clause's positive/negative balance. Themes include Teaching Clarity, Communication, Classroom Engagement, Lesson Pacing, Teaching Methodology, Classroom Interaction, Learning Materials, Assessment Methods, Instructor Responsiveness, Subject Comprehension, and Overall Satisfaction.

Each `feedback_themes` row stores theme, polarity, weight, and matched keywords. Excerpts are passed through `Security::anonymizeComment()`, which removes emails, PH-style mobile numbers, long digit strings, and @handles before storage in `sentiment_results.excerpt`. The original answer remains in `response_answers` for authorized review; students are warned not to include identifying details.

### Summary narrative

`AIEngine::summarize()` produces:

- Dominant sentiment (mixed is forced dominant when mixed, positive, and negative all exist).
- Strengths and concerns by theme count.
- Low categories below 3.50.
- Up to five evidence-linked recommendations.
- A narrative that explicitly says findings are rule-assisted signals, not a substitute for academic judgment.
- A caution when responses are below the privacy threshold.

### Known limits

- Sarcasm, irony, code-switching beyond the lexicon, and domain-specific slang are not reliably detected.
- Lexicon coverage is finite; Tagalog/Filipino coverage is partial.
- Negation scope is limited to two preceding tokens.
- Confidence is not calibrated against human labels.
- Themes are keyword associations, not semantic topic modeling.
- No accuracy, precision, recall, or F1 claim is made. A future validation study would be required.

## 3. Prediction and trend indicators

### Method

`PredictionService` uses:

1. **Period averages** — mean numeric rating per academic year + semester for one instructor.
2. **Least-squares slope** over the last `prediction_window` periods (default 8):
   ```
   slope = Σ((i - x̄)(yᵢ - ȳ)) / Σ((i - x̄)²)
   ```
3. **Weighted moving average** over the last up to four period averages, with weights 1, 2, 3, 4 (most recent highest).
4. **Forecast change** = weighted estimate − current period average.
5. **Negative feedback ratio** from analyzed comments.
6. **Consecutive declines** across recent periods.
7. **Lowest categories** below 3.50, shown as supporting context.

### Sufficiency gate

A risk indicator is produced only when:

- At least `prediction_min_periods` comparable periods exist (default 2), **and**
- At least `prediction_min_responses` responses exist (default 5).

Otherwise the stored result is `insufficient_data` with an explicit explanation and **no risk label**. The system does not fabricate a forecast from one period.

### Risk rules

`needs_attention` if any of:

- Slope ≤ `prediction_declining_slope` (default −0.08), or
- Forecast change ≤ −`prediction_forecast_change` (default −0.10), or
- Two or more consecutive period declines, or
- Negative comment ratio ≥ `prediction_negative_ratio` (default 0.30).

Otherwise `improving` if:

- Slope ≥ `prediction_improving_slope` (default 0.08), or
- Forecast change ≥ `prediction_forecast_change` (default 0.10).

Otherwise `stable`.

Every stored prediction includes `explanation`, `supporting_metrics`, `threshold_config`, `data_status`, method name, periods used, response count, and slope. The explanation ends with: “This is a rule-based estimate, not a validated machine-learning prediction.”

### What the numbers mean

| Field | Meaning |
|---|---|
| `current_average` | Most recent period mean on the 1–5 rating scale |
| `forecast_average` | Weighted recent level used as a forward estimate; not a guarantee |
| `slope` | Change in rating points per academic period |
| `record_count` | Periods used in the estimate |
| `response_count` | Responses available for the instructor |
| `data_status` | `sufficient` or `insufficient` |

### Known limits

- Period count is usually small; a two-period slope is fragile.
- Semester composition, course difficulty, and student cohorts are not controlled.
- Ratings are ordinal but treated numerically for means and slopes, a common but debatable practice.
- The engine does not establish causation or instructor effectiveness.
- No prediction accuracy has been validated. Do not present an “accuracy” figure.
- Results must be interpreted with academic judgment and context.

## 4. Recommendations

`RecommendationService` generates evidence-linked recommendations from:

- Negative theme mentions (any count).
- Category averages below 3.50.

Each recommendation stores the concern, recommendation, reason, supporting metric, priority (`high` when ≥3 negative mentions or average <3.0, otherwise `medium`), and status. Existing open recommendations are updated rather than duplicated. Recommendations are decision support, not disciplinary findings.

## 5. Reporting rules

When writing the manuscript or user documentation:

- Say “rule-based AI-assisted analysis” and “rule-based trend estimate.”
- Report the engine version (`rule-v1.1`) and threshold settings.
- Separate synthetic demo results from real evaluation results.
- Do not generalize from the demo dataset.
- Do not report the heuristic confidence as model accuracy.
- Report insufficient-data outputs as findings, not as missing analysis to be hidden.
