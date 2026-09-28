/** Read validation/business errors without exposing server diagnostics to the user. */
export function apiErrorMessage(error: unknown, fallback: string): string {
  const response = (error as { response?: { status?: number; data?: { message?: unknown; errors?: Record<string, unknown> } }; code?: string } | null)?.response;
  if (response?.status === 401) return "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.";
  if (response?.status === 429) return "Bạn thao tác quá nhanh. Vui lòng chờ một lát rồi thử lại.";
  if (response?.status && response.status >= 500) return fallback;
  const details = Object.values(response?.data?.errors ?? {}).flat().filter((item): item is string => typeof item === "string" && !!item.trim());
  if (details.length) return [...new Set(details)].join("\n");
  const message = response?.data?.message;
  if (typeof message === "string" && message.trim() && !/^(server error|network error|unauthenticated\.?|the given data was invalid\.?)$/i.test(message.trim())) return message;
  const code = (error as { code?: string } | null)?.code;
  if (code === "ERR_NETWORK") return `${fallback} Kiểm tra kết nối mạng rồi thử lại.`;
  if (code === "ECONNABORTED" || code === "ETIMEDOUT") return `${fallback} Kết nối quá lâu. Vui lòng thử lại.`;
  return fallback;
}
