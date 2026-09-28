import { useCallback, useEffect, useRef } from "react";

/** Ignore a preview that arrives after another selection, closing, or leaving the page. */
export function useLatestRequest() {
  const sequence = useRef(0);
  const begin = useCallback(() => ++sequence.current, []);
  const isCurrent = useCallback((id: number) => id === sequence.current, []);
  const invalidate = useCallback(() => { ++sequence.current; }, []);
  useEffect(() => invalidate, [invalidate]);
  return { begin, isCurrent, invalidate };
}
