import type { ReactNode } from "react";
import { Link, useSearchParams } from "react-router-dom";

type ResultContent = {
  accentClassName: string;
  description: string;
  icon: ReactNode;
  title: string;
};

const iconClass = "h-7 w-7";

const resultContent: Record<"success" | "failed", ResultContent> = {
  success: {
    accentClassName: "bg-green-100 text-green-700",
    description: "Booking của bạn đã được xác nhận.",
    icon: (
      <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
      </svg>
    ),
    title: "Thanh toán thành công",
  },
  failed: {
    accentClassName: "bg-red-100 text-red-700",
    description: "Booking chưa được xác nhận. Vui lòng thử lại hoặc liên hệ hỗ trợ.",
    icon: (
      <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2.2}
          d="M12 8v4m0 4h.01M12 3a9 9 0 110 18 9 9 0 010-18z"
        />
      </svg>
    ),
    title: "Thanh toán chưa hoàn tất",
  },
};

const PaymentActions = () => (
  <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
    <Link
      to="/tours"
      className="rounded-xl bg-primary-600 px-5 py-3 font-semibold text-white"
    >
      Xem tour khác
    </Link>
    <Link
      to="/"
      className="rounded-xl border px-5 py-3 font-semibold text-gray-700"
    >
      Về trang chủ
    </Link>
  </div>
);

export const PaymentResult = () => {
  const [searchParams] = useSearchParams();
  const bookingId = searchParams.get("booking_id");
  const status = searchParams.get("status") === "success" ? "success" : "failed";
  const content = resultContent[status];

  return (
    <div className="min-h-[70vh] bg-white flex items-center justify-center px-4">
      <div className="w-full max-w-xl rounded-xl border p-6 text-center">
        <div
          className={`mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full ${content.accentClassName}`}
        >
          {content.icon}
        </div>

        <h1 className="text-2xl font-bold">{content.title}</h1>
        <p className="mt-3 text-gray-600">{content.description}</p>

        {bookingId ? (
          <p className="mt-3 text-sm text-gray-500">Mã booking: #{bookingId}</p>
        ) : null}

        <PaymentActions />
      </div>
    </div>
  );
};

export default PaymentResult;
