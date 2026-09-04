import React, { useCallback, useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { ChatBubbleIcon, PaperPlaneIcon, XMarkIcon } from "@/components/Icons";
import { chatService, type ChatTourCard } from "@/services/chatService";

/**
 * Trợ lý ảo tư vấn tour — bong bóng chat góc màn hình.
 *
 * Câu trả lời đi kèm thẻ tour bấm được: chỉ trả lời bằng chữ thì khách phải tự gõ
 * lại tên tour vào ô tìm kiếm, và phần lớn sẽ không gõ.
 */

const TOKEN_KEY = "vivu_chat_token";

const GOI_Y = [
  "Tour biển 3 ngày dưới 5 triệu có không?",
  "Đi Hạ Long tháng này còn chỗ không?",
  "Hủy trước 10 ngày được hoàn bao nhiêu?",
];

interface ChatTurn {
  role: "user" | "assistant";
  content: string;
  tours?: ChatTourCard[];
}

const formatPrice = (value: number): string =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(value);

// sessionStorage chứ không localStorage: hội thoại thuộc về một lần ghé thăm.
// Bọc try vì trình duyệt chặn lưu trữ (chế độ riêng tư) thì widget vẫn phải chạy.
const readToken = (): string | null => {
  try {
    return sessionStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
};

const writeToken = (token: string): void => {
  try {
    sessionStorage.setItem(TOKEN_KEY, token);
  } catch {
    /* không lưu được thì hội thoại chỉ sống trong lần mở này */
  }
};

const TourSuggestion: React.FC<{ tour: ChatTourCard }> = ({ tour }) => (
  <Link
    to={`/tours/${tour.slug}`}
    className="flex items-center gap-3 rounded-lg border border-hairline bg-canvas p-2 transition-colors hover:border-primary-300 hover:bg-primary-50"
  >
    {tour.thumbnail ? (
      <img
        src={tour.thumbnail}
        alt=""
        className="h-12 w-12 shrink-0 rounded-md object-cover"
        loading="lazy"
      />
    ) : (
      <div className="h-12 w-12 shrink-0 rounded-md bg-surface-strong" />
    )}

    <div className="min-w-0 flex-1">
      <div className="truncate text-body-sm font-semibold text-ink">{tour.title}</div>
      <div className="text-caption-sm text-muted">
        {tour.number_of_days} ngày {tour.number_of_nights} đêm ·{" "}
        <span className="font-semibold text-primary-600">{formatPrice(tour.adult_price)}</span>
      </div>
    </div>
  </Link>
);

const TypingDots: React.FC = () => (
  <div className="flex items-center gap-1 px-1 py-2" aria-label="Trợ lý đang soạn câu trả lời">
    {[0, 150, 300].map((delay) => (
      <span
        key={delay}
        className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-soft"
        style={{ animationDelay: `${delay}ms` }}
      />
    ))}
  </div>
);

export const TourChatWidget: React.FC = () => {
  const [open, setOpen] = useState(false);
  const [turns, setTurns] = useState<ChatTurn[]>([]);
  const [draft, setDraft] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const tokenRef = useRef<string | null>(readToken());
  const scrollRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
  }, [turns, sending]);

  useEffect(() => {
    if (!open) return;

    inputRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [open]);

  const send = useCallback(
    async (message: string) => {
      const cauHoi = message.trim();

      if (!cauHoi || sending) return;

      setDraft("");
      setError(null);
      setTurns((prev) => [...prev, { role: "user", content: cauHoi }]);
      setSending(true);

      try {
        const reply = await chatService.ask(cauHoi, tokenRef.current);

        tokenRef.current = reply.conversation_token;
        writeToken(reply.conversation_token);

        setTurns((prev) => [
          ...prev,
          { role: "assistant", content: reply.answer, tours: reply.tours },
        ]);
      } catch (err) {
        // Câu hỏi vừa gửi vẫn nằm lại trong khung để khách đọc được và bấm gửi lại.
        setError(err instanceof Error ? err.message : "Có lỗi xảy ra, bạn thử lại nhé.");
      } finally {
        setSending(false);
      }
    },
    [sending],
  );

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="Mở trợ lý tư vấn tour"
        className="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition-colors hover:bg-primary-700"
      >
        <ChatBubbleIcon className="h-6 w-6" />
      </button>
    );
  }

  return (
    <div
      role="dialog"
      aria-label="Trợ lý tư vấn tour"
      className="animate-fade-in fixed bottom-5 right-5 z-50 flex h-[560px] max-h-[calc(100vh-2.5rem)] w-[380px] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-xl border border-hairline bg-canvas shadow-lg"
    >
      <header className="flex items-center justify-between border-b border-hairline-soft bg-primary-600 px-4 py-3 text-white">
        <div>
          <div className="text-title-sm font-semibold">Trợ lý tư vấn tour</div>
          <div className="text-caption-sm text-white/80">Hỏi về tour, giá, lịch khởi hành</div>
        </div>

        <button
          type="button"
          onClick={() => setOpen(false)}
          aria-label="Đóng trợ lý"
          className="rounded-full p-1.5 transition-colors hover:bg-white/15"
        >
          <XMarkIcon className="h-5 w-5" />
        </button>
      </header>

      <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto bg-surface-soft px-3 py-4">
        {turns.length === 0 && (
          <div className="space-y-3">
            <p className="rounded-lg bg-canvas px-3 py-2 text-body-sm text-body shadow-xs">
              Chào bạn, mình là trợ lý của Vivu Booking. Bạn muốn đi đâu, đi mấy ngày và ngân sách
              khoảng bao nhiêu?
            </p>

            <div className="space-y-2">
              {GOI_Y.map((cauHoi) => (
                <button
                  key={cauHoi}
                  type="button"
                  onClick={() => void send(cauHoi)}
                  className="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-left text-body-sm text-body transition-colors hover:border-primary-300 hover:text-primary-700"
                >
                  {cauHoi}
                </button>
              ))}
            </div>
          </div>
        )}

        {turns.map((turn, index) => (
          <div key={index} className={turn.role === "user" ? "flex justify-end" : "space-y-2"}>
            <div
              className={
                turn.role === "user"
                  ? "max-w-[85%] rounded-xl rounded-br-sm bg-primary-600 px-3 py-2 text-body-sm text-white"
                  : "max-w-[92%] whitespace-pre-wrap rounded-xl rounded-bl-sm bg-canvas px-3 py-2 text-body-sm text-body shadow-xs"
              }
            >
              {turn.content}
            </div>

            {turn.tours && turn.tours.length > 0 && (
              <div className="space-y-2">
                {turn.tours.map((tour) => (
                  <TourSuggestion key={tour.id} tour={tour} />
                ))}
              </div>
            )}
          </div>
        ))}

        {sending && <TypingDots />}

        {error && (
          <p className="rounded-lg bg-red-50 px-3 py-2 text-body-sm text-red-700">{error}</p>
        )}
      </div>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          void send(draft);
        }}
        className="flex items-center gap-2 border-t border-hairline-soft bg-canvas px-3 py-3"
      >
        <input
          ref={inputRef}
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          maxLength={1000}
          placeholder="Nhập câu hỏi của bạn..."
          aria-label="Câu hỏi cho trợ lý"
          className="input-field flex-1"
        />

        <button
          type="submit"
          disabled={sending || draft.trim().length === 0}
          aria-label="Gửi câu hỏi"
          className="btn-primary flex h-10 w-10 shrink-0 items-center justify-center !rounded-full !p-0"
        >
          <PaperPlaneIcon className="h-4 w-4" />
        </button>
      </form>

      <p className="border-t border-hairline-soft bg-canvas px-3 pb-2 text-caption-sm text-muted-soft">
        Thông tin mang tính tham khảo. Giá và chỗ trống chốt theo màn hình đặt tour.
      </p>
    </div>
  );
};

export default TourChatWidget;
