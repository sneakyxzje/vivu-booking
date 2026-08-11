export const formatPrice = (v: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(v);

// Chấp nhận "YYYY-MM-DD", "YYYY-MM-DD HH:mm:ss" và "YYYY-MM-DDTHH:mm";
// ngày không kèm giờ được neo về 00:00 giờ địa phương (tránh lệch múi giờ UTC).
export const parseDate = (d: string) => {
  const normalized = d.trim().replace(" ", "T");
  return new Date(normalized.length === 10 ? `${normalized}T00:00:00` : normalized);
};

const hasTimeComponent = (d: string) => /[T ]\d{2}:\d{2}/.test(d);

export const formatDate = (d: string) =>
  parseDate(d).toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });

export const formatDateTime = (d?: string | null) => {
  if (!d) return "-";

  const date = parseDate(d);
  if (Number.isNaN(date.getTime())) return d;
  if (!hasTimeComponent(d)) return formatDate(d);

  const time = date.toLocaleTimeString("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
  });

  return `${formatDate(d)} ${time}`;
};

export const getEndDate = (startDate: string, numberOfDays: number) => {
  const date = parseDate(startDate);
  date.setDate(date.getDate() + Math.max(0, numberOfDays - 1));
  return date.toLocaleDateString("vi-VN");
};
