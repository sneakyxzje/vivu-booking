import { useEffect, useState } from "react";
import { ArrowRight, Clock, Phone } from "lucide-react";
import guideService from "@/services/guideService";
import type { GuideHandoverNote } from "@/services/guideService";
import { formatDateTime } from "@/utils/format";

/**
 * Biên bản bàn giao đoàn.
 *
 * Hai chiều, và mỗi chiều phục vụ một việc khác nhau:
 *
 *   - **Nhận** — đoàn đang ở đâu, đã điểm danh tới đâu, khách nào cần để ý. Đây là thứ duy nhất
 *     người mới có để bắt nhịp, nên hiển thị nổi nhất.
 *   - **Giao** — người cũ mất quyền ghi nhưng vẫn đọc được mình đã giao gì, lúc nào. Không phải
 *     để can thiệp tiếp, mà để còn đối chiếu khi có khiếu nại về chặng mình từng dẫn.
 */
export default function GuideHandovers() {
  const [notes, setNotes] = useState<GuideHandoverNote[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        setNotes(await guideService.getMyHandovers());
      } catch (err) {
        console.error("Lỗi tải biên bản bàn giao:", err);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const nhan = notes.filter((n) => n.direction === "received");
  const giao = notes.filter((n) => n.direction === "given");

  const the = (note: GuideHandoverNote, nhanDoan: boolean) => (
    <div
      key={note.id}
      className={`rounded-xl border p-4 space-y-2 ${
        nhanDoan ? "border-primary-200 bg-primary-50/40" : "border-gray-200 bg-white"
      }`}
    >
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-bold text-gray-900">{note.tour_title}</span>
        <span className="text-xs text-gray-500">
          chuyến #{note.tour_schedule_id} · khởi hành {formatDateTime(note.start_date)}
        </span>
        <span className="ml-auto flex items-center gap-1 text-xs text-gray-500">
          <Clock className="h-3 w-3" />
          {formatDateTime(note.handed_over_at)}
        </span>
      </div>

      <p className="flex flex-wrap items-center gap-1.5 text-xs text-gray-600">
        <span className="font-semibold">{note.from_guide_name}</span>
        <ArrowRight className="h-3 w-3 text-gray-400" />
        <span className="font-semibold">{note.to_guide_name}</span>
        {!nhanDoan && note.to_guide_phone && (
          <span className="flex items-center gap-1 text-gray-500">
            <Phone className="h-3 w-3" />
            {note.to_guide_phone}
          </span>
        )}
      </p>

      <p className="text-xs text-gray-600">
        <span className="font-semibold">Lý do:</span> {note.reason}
      </p>

      <div className="rounded-lg bg-white/80 p-3 border border-gray-200">
        <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500">
          Tình trạng đoàn lúc bàn giao
        </p>
        <p className="mt-1 text-sm text-gray-800">{note.handover_note}</p>
      </div>
    </div>
  );

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Bàn giao đoàn</h1>
        <p className="text-sm text-gray-500 mt-1">
          Đoàn nhận lại từ người khác, và đoàn mình đã giao đi. Chỉ điều hành thực hiện bàn giao;
          ở đây bạn đọc lại nội dung.
        </p>
      </div>

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && notes.length === 0 && (
        <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
          Chưa có bàn giao nào.
        </p>
      )}

      {nhan.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">Đoàn bạn nhận ({nhan.length})</h2>
          {nhan.map((note) => the(note, true))}
        </div>
      )}

      {giao.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">Đoàn bạn đã giao ({giao.length})</h2>
          <p className="text-xs text-gray-500">
            Bạn không ghi tiếp được vào những chuyến này, nhưng vẫn xem lại được nội dung đã bàn giao.
          </p>
          {giao.map((note) => the(note, false))}
        </div>
      )}
    </div>
  );
}
