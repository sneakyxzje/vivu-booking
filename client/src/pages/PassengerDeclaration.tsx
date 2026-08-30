import { useCallback, useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { AlertTriangle, CalendarClock, Check, Users } from "lucide-react";
import api from "@/services/api";
import { formatDateTime } from "@/utils/format";
import { DateTimePicker } from "@/components/DateTimePicker";

/**
 * Khai danh sách hành khách sau khi đã đặt chỗ.
 *
 * Mở bằng mã tra cứu, **không cần đăng nhập** — vì đặt tour vốn không cần tài khoản. Trước đây
 * đường sửa hành khách nằm sau `role:customer`, nên khách vãng lai đặt xong là vĩnh viễn không
 * sửa được danh sách.
 *
 * Trang này tồn tại vì lúc bấm đặt, người đại diện thường chưa có số căn cước và ngày sinh của
 * cả nhóm. Bắt điền đủ trước khi thanh toán là bắt họ bỏ dở giỏ hàng đi hỏi từng người.
 */

type Row = {
  name: string;
  type: "adult" | "child" | "infant";
  gender: string;
  date_of_birth: string;
  id_type: string;
  identity_number: string;
  phone: string;
  special_request: string;
  is_contact: boolean;
};

type ServerPassenger = Partial<Row> & { name?: string };

interface DeclarationData {
  booking: {
    public_token: string;
    tour_title: string | null;
    departure_date: string | null;
    contact_name: string;
    contact_phone: string | null;
    status: string;
  };
  passengers: ServerPassenger[];
  guests: number;
  adult_count: number;
  child_count: number;
  infant_count: number;
  can_edit: boolean;
  locked_reason: string | null;
  deadline: string | null;
  warnings: string[];
}

const dongTrong = (type: Row["type"]): Row => ({
  name: "",
  type,
  gender: "",
  date_of_birth: "",
  id_type: "cccd",
  identity_number: "",
  phone: "",
  special_request: "",
  is_contact: false,
});

export default function PassengerDeclaration() {
  const { publicToken = "" } = useParams();

  const [data, setData] = useState<DeclarationData | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError("");

    try {
      const res = await api.get(`/bookings/${publicToken}/passengers`);
      const payload: DeclarationData = res.data?.data;
      setData(payload);

      /*
       * Dựng đúng số dòng theo số khách đã đặt, giữ lại những gì đã khai.
       *
       * Loại khách (người lớn / trẻ em / em bé) lấy theo số lượng lúc đặt chứ không cho đổi ở
       * đây: đổi loại là đổi giá, mà giá đã chốt và đã thanh toán rồi.
       */
      const khung: Row["type"][] = [
        ...Array.from({ length: payload.adult_count }, () => "adult" as const),
        ...Array.from({ length: payload.child_count }, () => "child" as const),
        ...Array.from({ length: payload.infant_count }, () => "infant" as const),
      ];

      setRows(
        khung.map((type, i) => {
          const daKhai = payload.passengers[i];

          return {
            ...dongTrong(type),
            ...(daKhai ?? {}),
            type,
            // Chưa khai ai thì điền sẵn người đại diện vào dòng đầu — họ vừa khai tên lúc đặt,
            // bắt gõ lại là vô lý. Vẫn sửa được.
            name: daKhai?.name ?? (i === 0 ? payload.booking.contact_name : ""),
            phone: daKhai?.phone ?? (i === 0 ? payload.booking.contact_phone ?? "" : ""),
            is_contact: daKhai?.is_contact ?? i === 0,
          } as Row;
        }),
      );
    } catch {
      setError("Không tìm thấy đơn với mã này. Kiểm tra lại liên kết trong thư xác nhận.");
    } finally {
      setLoading(false);
    }
  }, [publicToken]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const sua = (index: number, field: keyof Row, value: string | boolean) => {
    setSaved(false);
    setRows((truoc) =>
      truoc.map((row, i) => {
        // Chỉ một người là đầu mối liên hệ; chọn người mới thì bỏ người cũ.
        if (i !== index) {
          return field === "is_contact" && value === true ? { ...row, is_contact: false } : row;
        }
        return { ...row, [field]: value };
      }),
    );
  };

  const luu = async () => {
    setSaving(true);
    setError("");

    try {
      await api.put(`/bookings/${publicToken}/passengers`, {
        passengers: rows
          .filter((row) => row.name.trim())
          .map((row) => ({
            name: row.name.trim(),
            type: row.type,
            gender: row.gender || null,
            date_of_birth: row.date_of_birth || null,
            id_type: row.identity_number.trim() ? row.id_type : null,
            identity_number: row.identity_number.trim() || null,
            phone: row.phone.trim() || null,
            special_request: row.special_request.trim() || null,
            is_contact: row.is_contact,
          })),
      });

      setSaved(true);
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
        ?.response?.data;
      const loiDauTien = response?.errors ? Object.values(response.errors)[0]?.[0] : null;
      setError(loiDauTien || response?.message || "Không lưu được danh sách.");
    } finally {
      setSaving(false);
    }
  };

  const nhanLoai: Record<Row["type"], string> = {
    adult: "Người lớn",
    child: "Trẻ em",
    infant: "Em bé",
  };

  if (loading) {
    return <div className="mx-auto max-w-3xl px-4 py-16 text-body-sm text-muted">Đang tải...</div>;
  }

  if (!data) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <p className="rounded-lg bg-rose-50 px-4 py-3 text-body-sm text-rose-800">{error}</p>
        <Link to="/booking-lookup" className="mt-4 inline-block text-body-sm text-primary-600 hover:underline">
          Tra cứu đơn bằng mã →
        </Link>
      </div>
    );
  }

  const daKhaiDu = rows.filter((r) => r.name.trim()).length;

  return (
    <div className="mx-auto max-w-3xl px-4 py-10 space-y-6">
      <div>
        <h1 className="text-display-lg text-ink">Khai thông tin hành khách</h1>
        <p className="text-body-sm text-muted mt-1">
          {data.booking.tour_title} · khởi hành {formatDateTime(data.booking.departure_date ?? "")} ·{" "}
          {data.guests} khách
        </p>
      </div>

      {/*
        Hạn chốt đặt lên đầu, không giấu ở cuối. Đây là mốc khách mất quyền tự sửa — sau đó phải
        gọi điều hành — nên nó là thông tin quan trọng nhất trên trang này.
      */}
      {data.deadline && data.can_edit && (
        <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
          <CalendarClock className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
          <p className="text-body-sm text-amber-900">
            Cần khai xong trước <b>{formatDateTime(data.deadline)}</b>. Sau mốc đó danh sách đã gửi
            khách sạn và nhà xe, muốn sửa phải liên hệ điều hành.
          </p>
        </div>
      )}

      {!data.can_edit && (
        <div className="flex gap-3 rounded-xl border border-gray-200 bg-surface-soft px-4 py-3">
          <AlertTriangle className="w-5 h-5 text-muted shrink-0 mt-0.5" />
          <p className="text-body-sm text-body">{data.locked_reason}</p>
        </div>
      )}

      {saved && (
        <p className="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-body-sm text-emerald-900">
          <Check className="w-4 h-4" />
          Đã lưu danh sách. Bạn quay lại sửa bất cứ lúc nào trước hạn chốt.
        </p>
      )}

      {data.warnings.length > 0 && data.can_edit && (
        <ul className="space-y-1.5">
          {data.warnings.map((w) => (
            <li key={w} className="text-body-sm text-amber-800">
              {w}
            </li>
          ))}
        </ul>
      )}

      <p className="flex items-center gap-2 text-caption text-muted">
        <Users className="w-4 h-4" />
        Đã khai {daKhaiDu} / {data.guests} người
      </p>

      <div className="space-y-4">
        {rows.map((row, index) => (
          <div key={index} className="card-surface p-5 space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-primary-600 text-white text-badge flex items-center justify-center">
                {index + 1}
              </span>
              <span className="text-title-sm text-ink">{nhanLoai[row.type]}</span>

              <label className="ml-auto flex items-center gap-2 text-body-sm text-body cursor-pointer">
                <input
                  type="radio"
                  name="contact"
                  checked={row.is_contact}
                  disabled={!data.can_edit}
                  onChange={() => sua(index, "is_contact", true)}
                  className="h-4 w-4"
                />
                Hướng dẫn viên gọi người này
              </label>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="field-label">Họ và tên</label>
                <input
                  type="text"
                  value={row.name}
                  disabled={!data.can_edit}
                  onChange={(e) => sua(index, "name", e.target.value)}
                  placeholder="Như trên giấy tờ tùy thân"
                  className="input-field disabled:bg-surface-soft"
                />
              </div>

              <div>
                <label className="field-label">Ngày sinh</label>
                {/* Chế độ ngày sinh: chọn năm và tháng bằng ô danh sách, không bấm lùi từng tháng. */}
                <DateTimePicker
                  mode="birthday"
                  value={row.date_of_birth}
                  disabled={!data.can_edit}
                  onChange={(giaTri) => sua(index, "date_of_birth", giaTri)}
                  placeholder="Chọn ngày sinh"
                  buttonClassName="input-field flex w-full items-center gap-2 text-left disabled:bg-surface-soft disabled:cursor-not-allowed"
                />
              </div>

              <div>
                <label className="field-label">Giới tính</label>
                <select
                  value={row.gender}
                  disabled={!data.can_edit}
                  onChange={(e) => sua(index, "gender", e.target.value)}
                  className="input-field disabled:bg-surface-soft cursor-pointer"
                >
                  <option value="">— Chọn —</option>
                  <option value="male">Nam</option>
                  <option value="female">Nữ</option>
                  <option value="other">Khác</option>
                </select>
              </div>

              <div>
                <label className="field-label">Điện thoại</label>
                <input
                  type="tel"
                  value={row.phone}
                  disabled={!data.can_edit}
                  onChange={(e) => sua(index, "phone", e.target.value)}
                  className="input-field disabled:bg-surface-soft"
                />
              </div>

              {/*
                Em bé thường chưa có giấy tờ riêng, nên chỉ hỏi giấy tờ với người lớn và trẻ em.
                Hỏi thứ người ta không có là bắt họ để trống rồi tự hỏi mình có sai không.
              */}
              {row.type !== "infant" && (
                <>
                  <div>
                    <label className="field-label">Loại giấy tờ</label>
                    <select
                      value={row.id_type}
                      disabled={!data.can_edit}
                      onChange={(e) => sua(index, "id_type", e.target.value)}
                      className="input-field disabled:bg-surface-soft cursor-pointer"
                    >
                      <option value="cccd">Căn cước công dân</option>
                      <option value="passport">Hộ chiếu</option>
                      <option value="birth_certificate">Giấy khai sinh</option>
                    </select>
                  </div>

                  <div>
                    <label className="field-label">Số giấy tờ</label>
                    <input
                      type="text"
                      value={row.identity_number}
                      disabled={!data.can_edit}
                      onChange={(e) => sua(index, "identity_number", e.target.value)}
                      className="input-field disabled:bg-surface-soft"
                    />
                  </div>
                </>
              )}

              <div className="sm:col-span-2">
                <label className="field-label">Yêu cầu riêng</label>
                <input
                  type="text"
                  value={row.special_request}
                  disabled={!data.can_edit}
                  onChange={(e) => sua(index, "special_request", e.target.value)}
                  placeholder="Ăn chay, dị ứng hải sản, cần hỗ trợ di chuyển..."
                  className="input-field disabled:bg-surface-soft"
                />
              </div>
            </div>
          </div>
        ))}
      </div>

      {error && (
        <p className="rounded-lg bg-rose-50 px-4 py-3 text-body-sm font-medium text-rose-800">{error}</p>
      )}

      {data.can_edit && (
        <div className="flex flex-wrap items-center gap-3">
          <button type="button" onClick={luu} disabled={saving} className="btn-primary disabled:opacity-40">
            {saving ? "Đang lưu..." : "Lưu danh sách"}
          </button>

          {/* Khai một phần vẫn lưu được: có tên ai thì điền tên người đó trước. */}
          <span className="text-body-sm text-muted">
            Chưa đủ thông tin cũng lưu được, quay lại điền nốt sau.
          </span>
        </div>
      )}

      <Link
        to={`/booking-lookup?code=${data.booking.public_token}`}
        className="inline-block text-body-sm text-primary-600 hover:underline"
      >
        ← Xem lại đơn đặt tour
      </Link>
    </div>
  );
}
