<?php
declare(strict_types=1);
?>
<style>
/* =============================================================================
   Стили админки. Применяются только на ?page=admin.
   ============================================================================= */

.pzk-table th,
.pzk-table td {
    padding: 6px 10px;
    line-height: 1.4;
}
.pzk-table thead th {
    padding-top: 6px;
    padding-bottom: 6px;
    font-size: 0.72rem;
}
.pzk-table th:first-child,
.pzk-table td:first-child { padding-left: 14px; }
.pzk-table th:last-child,
.pzk-table td:last-child  { padding-right: 14px; }

.pzk-table-wrap {
    overflow: auto;
    border: 1px solid rgba(26, 26, 26, 0.1);
    background: #fff;
}
.pzk-table-wrap > .pzk-table { border: none; }

.pzk-table-wrap--subjects {
    overflow: visible;
}

.pzk-table td.pzk-table__actions {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.pzk-table td.pzk-table__actions form { display: inline-block; margin: 0; }
.pzk-table td.pzk-table__actions > button { white-space: nowrap; }

.pzk-table__empty {
    text-align: center;
    color: var(--color-tertiary);
    font-style: italic;
    padding: 14px 12px !important;
}

.pzk-table--lessons td.pzk-table__row-form {
    padding: 5px 14px;
    background: #fbfaf5;
}
.pzk-lesson-form {
    display: grid;
    grid-template-columns: 60px 100px 1fr 1fr 120px;
    gap: 6px;
    align-items: center;
    width: 100%;
}
.pzk-lesson-delete { display: block; margin-top: 4px; }

.pzk-table td code {
    background: rgba(139, 105, 20, 0.08);
    padding: 1px 5px;
    border-radius: 2px;
    font-size: 0.85em;
}
.pzk-table code.pzk-ip {
    background: rgba(26, 26, 26, 0.05);
    font-size: 0.75rem;
}

.pzk-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}
.pzk-status--active   { background: rgba(22, 163, 74, 0.12); color: #15803d; }
.pzk-status--inactive { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }

.pzk-sem-tag {
    display: inline-block;
    padding: 1px 8px;
    margin-left: 6px;
    border-radius: 10px;
    font-size: 0.68rem;
    background: rgba(139, 105, 20, 0.12);
    color: #6b5010;
    border: 1px solid rgba(139, 105, 20, 0.25);
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pzk-hint-inline {
    font-size: 0.78rem;
    color: var(--color-tertiary);
    padding: 4px 2px;
    display: inline-block;
}

.pzk-inline-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    padding: 8px 10px;
    background: var(--color-light);
    border: 1px solid rgba(26, 26, 26, 0.08);
}
.pzk-inline-form label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    color: var(--color-secondary);
}

.pzk-row-details summary {
    list-style: none;
    cursor: pointer;
}
.pzk-row-details summary::-webkit-details-marker { display: none; }
.pzk-row-details summary::marker { content: ''; }
.pzk-row-details__form {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    align-items: center;
    flex-wrap: wrap;
}
.pzk-row-details__form input {
    padding: 3px 7px;
    font-size: 0.8rem;
    min-width: 140px;
}
.pzk-edit-form {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fff;
    padding: 10px;
    border: 1px solid rgba(26, 26, 26, 0.1);
    min-width: 260px;
    margin-top: 4px;
}
.pzk-edit-form .pzk-field { gap: 3px; }
.pzk-edit-form .pzk-field__label { font-size: 0.78rem; }
.pzk-edit-form .pzk-field__input { padding: 4px 8px; font-size: 0.85rem; }
.pzk-edit-form .pzk-btn { align-self: flex-start; margin-top: 4px; }

.pzk-details {
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    box-shadow: 0 2px 8px rgba(80, 60, 20, 0.06);
    margin-bottom: var(--space-sm);
    transition: box-shadow 0.2s;
}
.pzk-details[open] {
    box-shadow: 0 4px 18px rgba(80, 60, 20, 0.10);
}

.pzk-details__summary {
    padding: 10px 14px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
    background: linear-gradient(135deg, #fbf7ec 0%, #f5ecd5 100%);
    cursor: pointer;
    user-select: none;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s, color 0.2s;
}
.pzk-details__summary::-webkit-details-marker { display: none; }
.pzk-details__summary::marker { content: ''; }
.pzk-details__summary:hover {
    background: linear-gradient(135deg, #f5ecd5 0%, #efe2c0 100%);
    color: #4a3a10;
}
.pzk-details[open] .pzk-details__summary {
    border-bottom: 1px solid #e8dcc0;
}

.pzk-form__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: #8b1a1a;
    letter-spacing: 0.02em;
    margin: 0 0 4px;
    line-height: 1.2;
}
.pzk-form__hint {
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    color: #6b5d3f;
    font-style: italic;
    line-height: 1.4;
    margin: 0;
}

.pzk-form {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pzk-form__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px 14px;
}

.pzk-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pzk-field__label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: none;
    color: #6b5010;
    margin: 0;
}
.pzk-field__input {
    padding: 8px 12px;
    border: 1px solid #d4c8a8;
    background: #ffffff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 1.02rem;
    color: #1a1a1a;
    transition: border-color 0.15s, box-shadow 0.15s;
    border-radius: 2px;
    width: 100%;
}
.pzk-field__input:focus {
    outline: none;
    border-color: #8b6914;
    box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
}
.pzk-field__input::placeholder {
    color: #b8a888;
    font-style: italic;
    font-size: 0.95rem;
}
.pzk-field__input--sm {
    padding: 4px 8px;
    font-size: 0.85rem;
}
.pzk-field__textarea {
    padding: 8px 12px;
    border: 1px solid #d4c8a8;
    background: #ffffff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 1rem;
    line-height: 1.5;
    color: #1a1a1a;
    transition: border-color 0.15s, box-shadow 0.15s;
    border-radius: 2px;
    width: 100%;
    min-height: 120px;
    resize: vertical;
    display: block;
}
.pzk-field__textarea:focus {
    outline: none;
    border-color: #8b6914;
    box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
}
.pzk-field__textarea::placeholder {
    color: #b8a888;
    font-style: italic;
}

.pzk-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Inter', sans-serif;
    font-size: 0.98rem;
    color: #2d2d2d;
    cursor: pointer;
    padding: 2px 0;
}
.pzk-checkbox input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #8b6914;
    cursor: pointer;
    flex-shrink: 0;
}

.pzk-fieldset {
    border: 1px solid #e8dcc0;
    background: rgba(255, 255, 255, 0.6);
    padding: 10px 14px;
    border-radius: 2px;
    margin: 0;
}
.pzk-fieldset legend {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
    padding: 0 6px;
}
.pzk-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 4px 12px;
    max-height: 240px;
    overflow-y: auto;
    padding: 4px 0;
}

.pzk-btn {
    padding: 6px 14px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: none;
    border: 1px solid rgba(26, 26, 26, 0.2);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
    border-radius: 2px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
}
.pzk-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    color: #1a1a1a;
    border-color: rgba(139, 105, 20, 0.4);
}
.pzk-btn--primary {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
    box-shadow: 0 2px 6px rgba(139, 105, 20, 0.2);
}
.pzk-btn--primary:hover {
    background: #a67c00;
    border-color: #a67c00;
    color: #fff;
    box-shadow: 0 3px 10px rgba(139, 105, 20, 0.3);
}
.pzk-btn--sm {
    padding: 4px 10px;
    font-size: 0.88rem;
    letter-spacing: 0.03em;
}
.pzk-btn[disabled] {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}
.pzk-btn[disabled]:hover {
    background: #8b6914;
    border-color: #8b6914;
    color: #fff;
    box-shadow: none;
}

.pzk-form__actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding-top: 10px;
    border-top: 1px solid #e8dcc0;
    margin-top: 2px;
}

/* ===== Семестры и праздники ===== */
.pzk-year-block {
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    padding: 10px 14px;
    margin-bottom: var(--space-sm);
    border-radius: 2px;
}
.pzk-year-block__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
    padding-bottom: 8px;
    margin-bottom: 8px;
    border-bottom: 1px solid #e8dcc0;
}
.pzk-year-block__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: #6b5010;
}
.pzk-year-block__meta {
    display: block;
    font-size: 0.78rem;
    color: var(--color-tertiary);
    margin-top: 1px;
}

.pzk-sem-card {
    background: #fff;
    border: 1px solid rgba(26, 26, 26, 0.1);
    border-radius: 2px;
    padding: 8px 12px;
    margin-bottom: 8px;
}
.pzk-sem-card:last-child { margin-bottom: 0; }
.pzk-sem-card__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 6px;
    padding-bottom: 6px;
    border-bottom: 1px dashed #e8dcc0;
}
.pzk-sem-card__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 2.1rem;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.1;
}
.pzk-sem-card__meta {
    display: block;
    font-size: 0.75rem;
    color: var(--color-tertiary);
    margin-top: 1px;
}
.pzk-sem-empty {
    font-size: 0.85rem;
    color: var(--color-tertiary);
    font-style: italic;
    padding: 4px 2px;
}

.pzk-holiday-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.pzk-holiday-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 6px 3px 8px;
    background: #fef3c7;
    border: 1px solid #d97706;
    border-left: 3px solid #d97706;
    border-radius: 3px;
    font-family: 'Inter', sans-serif;
    font-size: 0.82rem;
    color: #78350f;
    white-space: nowrap;
}
.pzk-holiday-chip__date {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.pzk-holiday-chip__name {
    color: #92400e;
    padding-left: 5px;
    border-left: 1px solid rgba(217, 119, 6, 0.3);
}
.pzk-holiday-chip__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    padding: 0;
    margin-left: 2px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 2px;
    color: #b45309;
    font-size: 1rem;
    line-height: 1;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
}
.pzk-holiday-chip__remove:hover {
    background: rgba(220, 38, 38, 0.1);
    color: #b91c1c;
    border-color: rgba(220, 38, 38, 0.3);
}
.pzk-holiday-form {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-end;
    padding: 8px 10px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-radius: 2px;
}
.pzk-holiday-form .pzk-field__label { font-size: 0.92rem; }
.pzk-holiday-form .pzk-field__input { padding: 5px 9px; font-size: 0.9rem; }

/* ===== Строка редактирования предмета ===== */
.pzk-subject-edit-row > td {
    padding: 0 !important;
    background: #fbf7ec;
    border-bottom: 1px solid rgba(26, 26, 26, 0.1);
}
.pzk-subject-edit > summary { display: none; }
.pzk-subject-edit__form {
    padding: 12px 14px;
    background: #fbf7ec;
    border-left: 3px solid #8b6914;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.pzk-subject-edit__actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid #e8dcc0;
}

/* ===== Строка редактирования студента ===== */
.pzk-student-edit-row > td {
    padding: 0 !important;
    background: #fbf7ec;
    border-bottom: 1px solid rgba(26, 26, 26, 0.1);
}
.pzk-student-edit > summary { display: none; }
.pzk-student-edit__form {
    padding: 12px 14px;
    background: #fbf7ec;
    border-left: 3px solid #8b6914;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.pzk-student-edit__actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid #e8dcc0;
}

/* ===== Темы под предметом ===== */
.pzk-topics-row > td {
    padding: 0 !important;
    background: #fbf7ec;
    border-bottom: 1px solid rgba(26, 26, 26, 0.1);
}
.pzk-subject-topics {
    background: #fbf7ec;
    border-left: 3px solid #8b6914;
}
.pzk-subject-topics__summary {
    padding: 6px 14px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-size: 0.88rem;
    color: #6b5010;
    font-weight: 500;
    user-select: none;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background 0.15s;
}
.pzk-subject-topics__summary::-webkit-details-marker { display: none; }
.pzk-subject-topics__summary::marker { content: ''; }
.pzk-subject-topics__summary:hover {
    background: rgba(139, 105, 20, 0.08);
}
.pzk-subject-topics[open] .pzk-subject-topics__summary {
    border-bottom: 1px solid #e8dcc0;
    font-weight: 600;
}
.pzk-subject-topics__body {
    padding: 10px 14px 12px;
    background: #fff;
}
.pzk-topics-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    margin-bottom: 10px;
    border: 1px solid rgba(26, 26, 26, 0.1);
}
.pzk-topics-table th,
.pzk-topics-table td {
    padding: 5px 10px;
    border-bottom: 1px solid rgba(26, 26, 26, 0.08);
    text-align: left;
    vertical-align: top;
}
.pzk-topics-table thead th {
    background: rgba(139, 105, 20, 0.08);
    color: #6b5010;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pzk-topics-table td.pzk-table__actions {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.pzk-topics-forms {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pzk-topics-add-form {
    background: #fbf7ec !important;
    border: 1px solid #e8dcc0 !important;
    padding: 8px 10px !important;
}

/* ===== Inline-редактирование темы ===== */
.pzk-topic-edit { display: block; }
.pzk-topic-edit > summary {
    list-style: none;
    cursor: pointer;
    padding: 0;
    margin: 0;
    user-select: none;
}
.pzk-topic-edit > summary::-webkit-details-marker { display: none; }
.pzk-topic-edit > summary::marker { content: ''; }
.pzk-topic-edit > summary:hover { background: rgba(139, 105, 20, 0.05); }
.pzk-topic-edit[open] > summary { font-weight: 600; color: #6b5010; }
.pzk-topic-edit__form {
    margin-top: 6px;
    padding: 8px 10px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 3px solid #8b6914;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.pzk-topic-edit__form .pzk-field__textarea {
    min-height: 4.5em;
    font-size: 0.92rem;
    padding: 6px 10px;
    resize: vertical;
}
.pzk-topic-edit__actions {
    display: flex;
    justify-content: flex-end;
}

.pzk-topic-below { margin-top: 4px; }
.pzk-topic-below__summary {
    display: inline-block;
    font-family: 'Inter', sans-serif;
    font-size: 0.78rem;
    color: #8b6914;
    opacity: 0.75;
    padding: 1px 4px;
    border-radius: 2px;
    transition: opacity 0.15s, background-color 0.15s;
}
.pzk-topic-below__summary:hover {
    opacity: 1;
    background: rgba(139, 105, 20, 0.08);
}
.pzk-topic-below[open] .pzk-topic-below__summary {
    opacity: 1;
    font-weight: 600;
}

/* ===== КТП ===== */
.pzk-subject-ktp {
    background: #f5f0e0;
    border-left: 3px solid #8b1a1a;
}
.pzk-ktp__head {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 6px 10px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 3px solid #8b6914;
    border-bottom: 0;
    margin-bottom: 0;
    position: sticky;
    top: 0;
    z-index: 3;
}
.pzk-ktp__head--match {
    background: #f0fdf4;
    border-left-color: #16a34a;
}
.pzk-ktp__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.15rem;
    font-weight: 600;
    color: #6b5010;
}
.pzk-ktp__hours {
    font-size: 0.9rem;
    color: var(--color-secondary);
}
.pzk-ktp__match-tag {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 8px;
    background: rgba(22, 163, 74, 0.12);
    color: #15803d;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
}
.pzk-ktp__match-value {
    display: inline-block;
    background: rgba(22, 163, 74, 0.14);
    color: #15803d;
    padding: 1px 6px;
    border-radius: 3px;
    font-weight: 700;
}
.pzk-ktp__match-value strong { font-weight: 700; }
.pzk-ktp__holidays {
    font-size: 0.85rem;
    color: #b91c1c;
    font-family: 'Inter', sans-serif;
    padding: 2px 8px;
    background: rgba(220, 38, 38, 0.06);
    border-left: 3px solid #dc2626;
    border-radius: 2px;
    white-space: normal;
}
.pzk-ktp-table-wrap {
    overflow: visible;
    border: 1px solid rgba(26, 26, 26, 0.1);
    border-top: 0;
    background: #fff;
}
.pzk-ktp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.85rem;
}
.pzk-ktp-table th,
.pzk-ktp-table td {
    padding: 5px 10px;
    border-bottom: 1px solid rgba(26, 26, 26, 0.08);
    text-align: left;
    vertical-align: middle;
}
.pzk-ktp-table thead th {
    position: sticky;
    top: var(--ktp-head-h, 42px);
    z-index: 2;
    background: #fbf7ec;
    color: #6b5010;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-top: 1px solid #e8dcc0;
    border-bottom: 2px solid #e8dcc0;
}
.pzk-ktp-table tbody tr:hover {
    background: rgba(139, 105, 20, 0.03);
}
.pzk-ktp-table td.pzk-table__actions {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.pzk-ktp-filter {
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 0.85rem;
    padding: 4px 8px;
    border: 1px solid #d4c8a8;
    background: #fff;
    color: #1a1a1a;
    border-radius: 2px;
}

/* ===== Расписание ===== */
.pzk-schedule-table {
    table-layout: fixed;
    width: 100%;
}
.pzk-schedule-table th:nth-child(1),
.pzk-schedule-table td:nth-child(1) { width: 36px; text-align: center; }
.pzk-schedule-table th:nth-child(2),
.pzk-schedule-table td:nth-child(2) { width: 110px; }
.pzk-schedule-table th:nth-child(n+3),
.pzk-schedule-table td:nth-child(n+3) { width: 14.5%; }
.pzk-schedule-cell {
    padding: 4px 4px !important;
    vertical-align: top;
    background: #fffdf5;
}
.pzk-schedule-entry {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 1px 0;
}
.pzk-schedule-entry select {
    width: 100%;
    padding: 3px 6px !important;
    font-size: 0.8rem !important;
    line-height: 1.25;
}

/* ===== Узкие экраны ===== */
@media (max-width: 1200px) {
    .pzk-table-wrap--subjects { overflow: auto; }
    .pzk-ktp__head { position: static; }
    .pzk-ktp-table-wrap {
        overflow-x: auto;
        border-top: 1px solid rgba(26, 26, 26, 0.1);
    }
    .pzk-ktp-table thead th {
        position: static;
        border-top: 0;
    }
}

/* ===== Мобильный ===== */
@media (max-width: 900px) {
    .pzk-table th, .pzk-table td { padding: 5px 8px; }
    .pzk-table th:first-child, .pzk-table td:first-child { padding-left: 10px; }
    .pzk-table th:last-child,  .pzk-table td:last-child  { padding-right: 10px; }
    .pzk-lesson-form { grid-template-columns: 1fr; }
    .pzk-form { padding: 12px 12px; }
    .pzk-form__title { font-size: 1.25rem; }
    .pzk-details__summary { padding: 8px 12px; font-size: 1.05rem; }
    .pzk-form__actions { justify-content: stretch; }
    .pzk-form__actions .pzk-btn { width: 100%; justify-content: center; }

    .pzk-year-block { padding: 8px 10px; }
    .pzk-sem-card { padding: 8px 10px; }
    .pzk-sem-card__title { font-size: 1.6rem; }
    .pzk-holiday-form { flex-direction: column; align-items: stretch; }
    .pzk-holiday-form .pzk-field { flex: 1 1 auto !important; }

    .pzk-subject-edit__form { padding: 10px 12px; }
    .pzk-subject-edit__actions { justify-content: stretch; }
    .pzk-subject-edit__actions .pzk-btn { width: 100%; justify-content: center; }

    .pzk-student-edit__form { padding: 10px 12px; }
    .pzk-student-edit__actions { justify-content: stretch; }
    .pzk-student-edit__actions .pzk-btn { width: 100%; justify-content: center; }
}


/* ===== Строка редактирования группы ===== */
.pzk-group-edit-row > td {
    padding: 0 !important;
    background: #fbf7ec;
    border-bottom: 1px solid rgba(26, 26, 26, 0.1);
}
.pzk-group-edit > summary { display: none; }
.pzk-group-edit__form {
    padding: 12px 14px;
    background: #fbf7ec;
    border-left: 3px solid #8b6914;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.pzk-group-edit__actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid #e8dcc0;
}

@media (max-width: 900px) {
    .pzk-group-edit__form { padding: 10px 12px; }
    .pzk-group-edit__actions { justify-content: stretch; }
    .pzk-group-edit__actions .pzk-btn { width: 100%; justify-content: center; }
}

/* ===== Типы проектов ===== */
.pzk-ptype {
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    margin-bottom: var(--space-md);
    border-radius: 2px;
}
.pzk-ptype__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
    padding: 10px 14px;
    border-bottom: 1px solid #e8dcc0;
}
.pzk-ptype__title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.pzk-ptype__icon { font-size: 1.4rem; line-height: 1; }
.pzk-ptype__name {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.4rem;
    font-weight: 600;
    color: #6b5010;
    padding-left: 6px;
    border-left: 4px solid var(--type-color, #8b6914);
    line-height: 1.1;
}
.pzk-ptype__code {
    background: rgba(139, 105, 20, 0.08);
    padding: 1px 8px;
    border-radius: 3px;
    font-size: 0.82rem;
}
.pzk-ptype__folder {
    font-size: 0.72rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}
.pzk-ptype__folder--ok  { background: rgba(22, 163, 74, 0.12);  color: #15803d; }
.pzk-ptype__folder--bad { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }
.pzk-ptype__actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: flex-start;
}
.pzk-ptype__desc {
    padding: 6px 14px;
    font-size: 0.88rem;
    color: #6b5d3f;
    border-bottom: 1px solid #e8dcc0;
}
.pzk-ptype__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 0;
}
.pzk-ptype__grid > div {
    padding: 10px 14px;
}
.pzk-ptype__grid > div:first-child { border-right: 1px solid #e8dcc0; }
.pzk-ptype__section-title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: #6b5010;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.pzk-ptype__phase-color {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
}
.pzk-table--ptype-phases { font-size: 0.82rem; border: 1px solid rgba(26, 26, 26, 0.1); background: #fff; }
.pzk-table--ptype-phases th,
.pzk-table--ptype-phases td { padding: 5px 10px; vertical-align: middle; }
.pzk-table--ptype-phases thead th {
    background: rgba(139, 105, 20, 0.08);
    color: #6b5010;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pzk-table--ptype-checks { font-size: 0.8rem; width: 100%; border: 1px solid rgba(26, 26, 26, 0.1); background: #fff; }
.pzk-table--ptype-checks th,
.pzk-table--ptype-checks td { padding: 5px 10px; vertical-align: top; }
.pzk-table--ptype-checks thead th {
    background: rgba(139, 105, 20, 0.08);
    color: #6b5010;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

@media (max-width: 1100px) {
    .pzk-ptype__grid { grid-template-columns: 1fr; }
    .pzk-ptype__grid > div:first-child { border-right: none; border-bottom: 1px solid #e8dcc0; }
}
</style>