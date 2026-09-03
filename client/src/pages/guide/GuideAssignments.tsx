import { useCallback, useEffect, useState } from "react";
import { Calendar, Check, Clock, Users } from "lucide-react";
import guideService from "@/services/guideService";
import type { GuideAssignment } from "@/services/guideService";
import { formatDateTime, getEndDate } from "@/utils/format";

/**
 * Chuyến được phân công — hộp việc của hướng dẫn viên.
 *
 * Trước đây điều hành gán xong là coi như xong; hướng dẫn viên chỉ phát hiện khi tự mở danh sách
 * tour của mình, và muốn nói "hôm đó tôi bận" thì phải gọi điện.
 *
 * Điểm dễ hiểu nhầm, nên màn hình nói thẳng ra: **chưa xác nhận vẫn là đã được phân công.** Điều
 * hành đang trông vào bạn. Xác nhận chỉ là bằng chứng bạn đã biết; muốn không đi thì phải từ chối,
 * và từ chối thì phải nêu lý do.
 */
export default function GuideAssignments() {
  const [items, setItems] = useState<GuideAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState("");

  const [decliningId, setDecliningId] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      setItems(await guideService.getMyAssignments());
    } catch (err) {
      console.error("Lỗi tải chuyến được phân công:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const xacNhan = async (scheduleId: number) => {
    try {
      setNotice(await guideService.acceptAssignment(scheduleId));
      loadData();
    } catch (err) {
      console.error("Lỗi xác nhận:", err);
    }
  };

  const tuChoi = async () => {
    if (!decliningId || reason.trim().length < 10) return;

    setSaving(true);
    setError("");

    try {
      setNotice(await guideService.declineAssignment(decliningId, reason.trim()));
      setDecliningId(null);
      setReason("");
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không từ chối được.");
    } finally {
      setSaving(false);
    }
  };

  const chuaXacNhan = items.filter((item) => !item.accepted_at);
  const daXacNhan = items.filter((item) => item.accepted_at);

  const the = (item: GuideAssignment) => (
    <div
      key={item.schedule_id}
      className={`rounded-xl border p-4 space-y-2 ${
        item.accepted_at ? "border-gray-200 bg-white" : "border-primary-300 bg-primary-50/40"
      }`}
    >
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-bold text-gray-900">{item.tour_title}</span>
        <span className="text-xs text-gray-500">chuyến #{item.schedule_id}</span>
        <span className="ml-auto rounded bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">
          {item.status_label}
        </span>
      </div>

      <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
        <span className="flex items-center gap-1">
          <Calendar className="h-3 w-3" />
          {/*
            Ngày về đọc từ `end_date` của chuyến, chỉ suy từ số ngày tour khi cột ấy rỗng.

            Suy trọn gói là sai kể từ khi điều hành đặt được mốc kết thúc: chuyến xe đêm khởi hành
            22h và trả khách 5h sáng thì kết thúc vào ngày thứ tư của một tour ba ngày, còn phép
            suy vẫn nói ngày thứ ba. Người dẫn đoàn là người cuối cùng nên đọc sai ngày về.
          */}
          {formatDateTime(item.start_date)} —{" "}
          {item.end_date
            ? formatDateTime(item.end_date)
            : getEndDate(item.start_date, item.number_of_days)}
        </span>

        {/* Giờ áng chừng do điều hành điền. Chỉ hiện khi có — không đoán hộ. */}
        {item.arrival_at && (
          <span className="flex items-center gap-1">
            <Clock className="h-3 w-3" />
            Tới nơi: {formatDateTime(item.arrival_at)}
          </span>
        )}

        {item.return_departure_at && (
          <span className="flex items-center gap-1">
            <Clock className="h-3 w-3" />
            Rời điểm đến: {formatDateTime(item.return_departure_at)}
          </span>
        )}

        {item.co_guides.length > 0 && (
          <span className="flex items-center gap-1">
            <Users className="h-3 w-3" />
            Cùng dẫn: {item.co_guides.join(", ")}
          </span>
        )}
      </p>

      {item.accepted_at ? (
        <p className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
          <Check className="h-3.5 w-3.5" />
          Đã xác nhận lúc {formatDateTime(item.accepted_at)}
        </p>
      ) : decliningId === item.schedule_id ? (
        <div className="space-y-2">
          <textarea
            rows={2}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="VD: Tuần đó tôi đã có lịch gia đình, không đi được..."
            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
          />
          <p className="text-[11px] text-gray-400">
            Ít nhất 10 ký tự. Điều hành cần lý do để xếp người khác.
          </p>

          {error && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
              {error}
            </p>
          )}

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => {
                setDecliningId(null);
                setError("");
              }}
              disabled={saving}
              className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
              Bỏ qua
            </button>
            <button
              type="button"
              onClick={tuChoi}
              disabled={saving || reason.trim().length < 10}
              className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-40"
            >
              {saving ? "Đang gửi..." : "Xác nhận từ chối"}
            </button>
          </div>
        </div>
      ) : (
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => xacNhan(item.schedule_id)}
            className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700"
          >
            Xác nhận nhận chuyến
          </button>

          {item.can_decline ? (
            <button
              type="button"
              onClick={() => {
                setDecliningId(item.schedule_id);
                setReason("");
                setError("");
              }}
              className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
            >
              Từ chối
            </button>
          ) : (
            <span className="text-[11px] text-gray-500">
              Đoàn đã lên đường nên không từ chối được nữa. Không dẫn tiếp được thì gửi yêu cầu
              bàn giao.
            </span>
          )}
        </div>
      )}
    </div>
  );

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Chuyến được giao</h1>
        <p className="text-sm text-gray-500 mt-1">
          Chưa xác nhận vẫn là đã được phân công — điều hành đang trông vào bạn. Không đi được thì
          phải từ chối kèm lý do, đừng để im.
        </p>
      </div>

      {notice && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {notice}
        </div>
      )}

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && items.length === 0 && (
        <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
          Bạn chưa được phân công chuyến nào.
        </p>
      )}

      {chuaXacNhan.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">
            Chờ bạn trả lời ({chuaXacNhan.length})
          </h2>
          {chuaXacNhan.map(the)}
        </div>
      )}

      {daXacNhan.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">Đã xác nhận ({daXacNhan.length})</h2>
          {daXacNhan.map(the)}
        </div>
      )}
    </div>
  );
}
