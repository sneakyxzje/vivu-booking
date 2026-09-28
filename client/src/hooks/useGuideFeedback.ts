import { App } from "antd";
import { useEffect, useMemo, useRef } from "react";
import { apiErrorMessage } from "@/utils/apiErrorMessage";

export function useGuideFeedback() {
  const { message, notification } = App.useApp();
  const mounted = useRef(true);
  const loadKeys = useRef(new Set<string>());
  useEffect(() => {
    mounted.current = true;
    const keys = loadKeys.current;
    return () => {
      mounted.current = false;
      keys.forEach(key => notification.destroy(key));
      keys.clear();
    };
  }, [notification]);
  return useMemo(() => ({
    success: (content: string) => { void message.success(content); },
    warning: (content: string) => { void message.warning({ content, duration: 7 }); },
    error: (error: unknown, fallback: string) => {
      void message.error({ content: apiErrorMessage(error, fallback), duration: 7 });
    },
    loadError: (error: unknown, title: string) => {
      if (!mounted.current) return;
      const key = `guide-load-${title}`;
      loadKeys.current.add(key);
      notification.error({ key, title, description: apiErrorMessage(error, "Bạn có thể bấm Tải lại để thử lại."), duration: 0 });
    },
    clearLoadError: (title: string) => {
      const key = `guide-load-${title}`;
      notification.destroy(key);
      loadKeys.current.delete(key);
    },
  }), [message, notification]);
}
