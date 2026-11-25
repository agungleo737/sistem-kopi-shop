import { useState } from 'react';
import { normalizeQuantity } from '../lib/inventory.js';

export default function QuantityInput({ value, max, onCommit, label }) {
  const [draft, setDraft] = useState(null);
  const limit = Math.min(500, Math.max(0, Math.floor(Number(max) || 0)));
  const commit = () => {
    onCommit(normalizeQuantity(draft ?? value, limit));
    setDraft(null);
  };
  return (
    <input
      className="qty-input"
      type="number"
      inputMode="numeric"
      min="1"
      max={limit}
      step="1"
      aria-label={label}
      disabled={limit < 1}
      value={draft ?? value}
      onChange={(event) => setDraft(event.target.value)}
      onBlur={commit}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          event.currentTarget.blur();
        } else if (event.key === 'Escape') {
          setDraft(null);
        }
      }}
    />
  );
}
