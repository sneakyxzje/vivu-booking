import { lazy, Suspense, useCallback, useEffect, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft, Check, LockKeyhole } from "lucide-react";
import { Alert, Button, Collapse, DatePicker, Form, Input, Radio, Select, Skeleton, Tooltip } from "antd";
import dayjs from "dayjs";
import FieldHelp from "@/components/booking/FieldHelp";
import api from "@/services/api";
import { useAuth } from "@/hooks/useAuth";
import { formatDateTime } from "@/utils/format";
import PassengerEmailVerification from "@/components/booking/PassengerEmailVerification";
import { emptyPassenger, fillPassengerSlots } from "@/utils/passengerImport";
import type { PassengerRow as Row } from "@/utils/passengerImport";

const PassengerImport = lazy(() => import("@/components/booking/PassengerImport"));

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
  /** Số giấy tờ đang bị che vì người xem chưa xác thực OTP qua email đã đặt. */
  identity_masked: boolean;
  requires_otp: boolean;
}

const dongTrong = emptyPassenger;

export default function PassengerDeclaration() {
  const { user, isAuthenticated } = useAuth();
  const { publicToken } = useParams();
  // Đổi tài khoản/đăng xuất thì bỏ dữ liệu và quyền của phiên trước khỏi biểu mẫu.
  return <PassengerDeclarationForm key={`${publicToken}:${isAuthenticated ? user?.id : "guest"}`} />;
}

function PassengerDeclarationForm() {
  const { publicToken = "" } = useParams();

  const [data, setData] = useState<DeclarationData | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const accessToken = useRef("");
  const [accessExpiresAt, setAccessExpiresAt] = useState(0);
  const draftDirty = useRef(false);
  const [imported, setImported] = useState(false);
  const [saved, setSaved] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [activePassenger, setActivePassenger] = useState("0");
  const loadRequest = useRef(0);

  const requireVerification = useCallback(() => {
    accessToken.current = "";
    setAccessExpiresAt(0);
    setData(previous => previous ? { ...previous, requires_otp: true, identity_masked: true } : previous);
    setError("Phiên xác thực đã hết hạn. Xác thực lại OTP để tiếp tục; nội dung đang nhập vẫn được giữ.");
  }, []);

  useEffect(() => { draftDirty.current = dirty; }, [dirty]);

  useEffect(() => {
    if (!accessExpiresAt) return;
    const timer = window.setTimeout(requireVerification, Math.max(0, accessExpiresAt - Date.now()));
    return () => window.clearTimeout(timer);
  }, [accessExpiresAt, requireVerification]);

  const loadData = useCallback((signal?: AbortSignal, preserveDraft = false) => {
    const requestId = ++loadRequest.current;

    return api.get(`/bookings/${publicToken}/passengers`, {
      headers: accessToken.current ? { "X-Passenger-Access": accessToken.current } : undefined,
      signal,
    }).then(res => {
      const payload: DeclarationData = res.data?.data;
      if (requestId !== loadRequest.current || signal?.aborted) return;
      setData(payload);
      if (preserveDraft && draftDirty.current) return;
      setImported(false);
      setDirty(false);

      /*
       * Dựng đúng số dòng theo số khách đã đặt, giữ lại những gì đã khai.
       *
       * Loại khách (người lớn / trẻ em / em bé) lấy theo số lượng lúc đặt chứ không cho đổi ở
       * đây: đổi loại là đổi giá, mà giá đã chốt và đã thanh toán rồi.
       */
      const khung: Row["type"][] = [
        ...Array.from({ length: payload.adult_count }, () => "adult" as const),
        ...Array.from({ length: payload.child_count }, () => "child" as const),
        ...Array.from(
          { length: payload.infant_count },
          () => "infant" as const,
        ),
      ];

      const used = { adult: 0, child: 0, infant: 0 };
      setRows(
        khung.map((type, i) => {
          const daKhai = payload.passengers.filter(p => p.type === type)[used[type]++];

          return {
            ...dongTrong(type),
            type,
            // Chưa khai ai thì điền sẵn người đại diện vào dòng đầu — họ vừa khai tên lúc đặt,
            // bắt gõ lại là vô lý. Vẫn sửa được.
            name: daKhai?.name ?? (i === 0 ? payload.booking.contact_name : ""),
            phone:
              daKhai?.phone ??
              (i === 0 ? (payload.booking.contact_phone ?? "") : ""),
            gender: daKhai?.gender ?? "",
            date_of_birth: daKhai?.date_of_birth?.slice(0, 10) ?? "",
            id_type: daKhai?.id_type ?? "cccd",
            identity_number: daKhai?.identity_number ?? "",
            special_request: daKhai?.special_request ?? "",
            is_contact: daKhai?.is_contact ?? i === 0,
          } as Row;
        }),
      );
    }).catch(() => {
      if (requestId !== loadRequest.current || signal?.aborted) return;
      setData(null);
      setError(
        "Không tìm thấy đơn với mã này. Kiểm tra lại liên kết trong thư xác nhận.",
      );
    }).finally(() => {
      if (requestId === loadRequest.current && !signal?.aborted) setLoading(false);
    });
  }, [publicToken]);

  useEffect(() => {
    const controller = new AbortController();
    void loadData(controller.signal);
    return () => controller.abort();
  }, [loadData]);

  const sua = (index: number, field: keyof Row, value: string | boolean) => {
    setSaved(false);
    setDirty(true);
    setRows((truoc) =>
      truoc.map((row, i) => {
        // Chỉ một người là đầu mối liên hệ; chọn người mới thì bỏ người cũ.
        if (i !== index) {
          return field === "is_contact" && value === true
            ? { ...row, is_contact: false }
            : row;
        }
        return { ...row, [field]: value };
      }),
    );
  };

  const luu = async () => {
    if (!data?.can_edit || saving) return;
    if (data.identity_masked || data.requires_otp !== false) {
      setError("Xác thực OTP qua email trước khi lưu danh sách.");
      return;
    }

    const invalidPhoneRow = rows.find(
      (row) => row.phone && row.phone.trim() && !/^\+?[0-9]{8,20}$/.test(row.phone.replace(/[\s-]/g, "")),
    );
    if (invalidPhoneRow) {
      setError(`Số điện thoại của ${invalidPhoneRow.name || "hành khách"} không hợp lệ (8–20 chữ số, có thể bắt đầu bằng +).`);
      return;
    }

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
            /*
              Gửi đúng những gì biểu mẫu hỏi.
              Giữ lại giá trị của một ô đã bị ẩn thì nó còn hiện trên danh sách đoàn và trên hợp
              đồng, mà không ai sửa được nữa — tệ hơn là bỏ hẳn.
            */
            id_type:
              row.type === "adult" && row.identity_number.trim()
                ? row.id_type
                : null,
            identity_number:
              row.type === "adult" ? row.identity_number.trim() || null : null,
            phone: row.type === "infant" ? null : row.phone.trim() || null,
            special_request: row.special_request.trim() || null,
            is_contact: row.is_contact,
          })),
      }, { headers: accessToken.current ? { "X-Passenger-Access": accessToken.current } : undefined });

      setSaved(true);
      setLoading(true);
      await loadData();
    } catch (err) {
      const response = (
        err as {
          response?: {
            data?: { message?: string; code?: string; errors?: Record<string, string[]> };
          };
        }
      )?.response?.data;
      if (response?.code === "passenger_verification_required") {
        requireVerification();
        return;
      }
      const loiDauTien = response?.errors
        ? Object.values(response.errors)[0]?.[0]
        : null;
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

  if (loading || (data && data.booking.public_token !== publicToken)) return <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6"><Skeleton active paragraph={{ rows: 8 }} /></div>;
  if (!data) return <div className="mx-auto max-w-3xl px-4 py-12">
    <Alert type="error" showIcon title={error || "Không tải được thông tin đặt tour."} />
    <Link to="/booking-lookup" className="mt-5 inline-flex items-center gap-2 text-sm text-primary-700"><ArrowLeft size={16} /> Tra cứu đơn</Link>
  </div>;

  const namedCount = rows.filter(row => row.name.trim()).length;
  const identityVerified = data.requires_otp === false && !data.identity_masked;
  const readOnly = !data.can_edit || !identityVerified || saving;
  const onVerified = async (token: string, expiresIn: number) => {
    accessToken.current = token;
    setAccessExpiresAt(Date.now() + expiresIn * 1000);
    setError("");
    await loadData(undefined, true);
  };
  const inputId = (index: number, field: string) => `passenger-${index}-${field}`;

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
      <header className="mb-8">
        <Link to={`/booking-lookup?code=${data.booking.public_token}`} className="mb-5 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900">
          <ArrowLeft size={16} aria-hidden="true" /> Đơn đặt tour
        </Link>
        <h1 className="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Thông tin hành khách</h1>
        <p className="mt-2 text-sm text-slate-500">{data.booking.tour_title}</p>
      </header>

      <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
        <aside className="order-1 space-y-6 lg:order-2 lg:sticky lg:top-24" aria-label="Thông tin đặt tour">
          <section className="border-t-2 border-slate-900 pt-5">
            <h2 className="mb-5 text-sm font-semibold text-slate-900">Chuyến đi của bạn</h2>
            <dl className="space-y-4 text-sm">
              <div><dt className="mb-1 text-slate-500">Khởi hành</dt><dd className="font-medium text-slate-900">{formatDateTime(data.booking.departure_date ?? "")}</dd></div>
              <div><dt className="mb-1 text-slate-500">Số hành khách</dt><dd className="text-slate-900">{data.adult_count} người lớn{data.child_count > 0 && ` · ${data.child_count} trẻ em`}{data.infant_count > 0 && ` · ${data.infant_count} em bé`}</dd></div>
              {data.deadline && <div>
                <dt className="flex items-center gap-1 text-slate-500">Hạn khai thông tin<FieldHelp label="Về hạn khai thông tin">Sau mốc này, vui lòng liên hệ điều hành nếu cần sửa danh sách.</FieldHelp></dt>
                <dd className="font-medium text-slate-900">{formatDateTime(data.deadline)}</dd>
              </div>}
            </dl>
          </section>

          {data.requires_otp !== false
            ? <PassengerEmailVerification publicToken={publicToken} onVerified={onVerified} />
            : accessExpiresAt > 0 && <p className="flex items-center gap-2 text-sm text-teal-700"><Check size={16} aria-hidden="true" /> Đã xác thực email</p>}

        </aside>

        <section className="order-2 min-w-0 lg:order-1" aria-label="Danh sách hành khách">
          <div className="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 className="text-base font-semibold text-slate-900">Danh sách hành khách <span className="ml-1 font-normal text-slate-400">({data.guests})</span></h2>
              <p className="mt-1 text-xs text-slate-500">Đã có tên {namedCount}/{data.guests} người</p>
            </div>
            {data.can_edit && <Suspense fallback={<span className="text-sm text-slate-400">Đang tải...</span>}><PassengerImport
              key={`${publicToken}:${identityVerified}`}
              context={{ adult_count: data.adult_count, child_count: data.child_count, infant_count: data.infant_count, departure_date: data.booking.departure_date }}
              disabled={readOnly}
              onApply={passengers => {
                if (readOnly) return;
                setRows(fillPassengerSlots(passengers, { ...data, departure_date: data.booking.departure_date }));
                setSaved(false);
                setError("");
                setImported(true);
                setDirty(true);
                setActivePassenger("0");
              }}
            /></Suspense>}
          </div>

          {!data.can_edit && <div className="mb-5"><Alert type="info" showIcon title="Danh sách chỉ đọc" description={data.locked_reason} /></div>}
          {data.can_edit && !identityVerified && <p className="mb-4 flex items-center gap-2 text-sm text-slate-500"><LockKeyhole size={15} aria-hidden="true" /> Xác thực OTP qua email để chỉnh sửa.</p>}

          <Collapse accordion activeKey={activePassenger} onChange={keys => setActivePassenger(Array.isArray(keys) ? keys[0] ?? "" : keys)} expandIconPlacement="end" style={{ background: "white", borderColor: "#e2e8f0" }} items={rows.map((row, index) => ({
            key: String(index),
            label: <div className="flex min-w-0 items-center gap-3 py-1">
              <span className="w-5 shrink-0 text-xs tabular-nums text-slate-400">{String(index + 1).padStart(2, "0")}</span>
              <div className="min-w-0 flex-1">
                <span className={`block truncate text-sm ${row.name.trim() ? "font-medium text-slate-900" : "text-slate-500"}`}>{row.name.trim() || `Hành khách ${index + 1}`}</span>
                <span className="text-xs text-slate-500">{nhanLoai[row.type]}{row.is_contact && " · Người đại diện"}</span>
              </div>
            </div>,
            children: <Form layout="vertical" disabled={readOnly} requiredMark={false}>
              <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2 xl:grid-cols-4">
                <Form.Item htmlFor={inputId(index, "name")} label={<span className="inline-flex items-center">Họ và tên<FieldHelp label="Cách nhập họ tên">Nhập họ tên đúng như trên giấy tờ tùy thân.</FieldHelp></span>} className="sm:col-span-2">
                  <Input id={inputId(index, "name")} value={row.name} maxLength={255} autoComplete="off" placeholder="Họ và tên hành khách" onChange={event => sua(index, "name", event.target.value)} />
                </Form.Item>
                <Form.Item htmlFor={inputId(index, "dob")} label="Ngày sinh">
                  <DatePicker id={inputId(index, "dob")} value={row.date_of_birth ? dayjs(row.date_of_birth) : null} format="DD/MM/YYYY" maxDate={dayjs()} placeholder="Ngày / tháng / năm" style={{ width: "100%" }} onChange={date => sua(index, "date_of_birth", date?.format("YYYY-MM-DD") ?? "")} />
                </Form.Item>
                <Form.Item htmlFor={inputId(index, "gender")} label="Giới tính">
                  <Select id={inputId(index, "gender")} value={row.gender || undefined} allowClear placeholder="Chọn" options={[{ value: "male", label: "Nam" }, { value: "female", label: "Nữ" }, { value: "other", label: "Khác" }]} onChange={value => sua(index, "gender", value ?? "")} />
                </Form.Item>
                {row.type === "adult" && <>
                  <Form.Item htmlFor={inputId(index, "id-type")} label="Loại giấy tờ">
                    <Select id={inputId(index, "id-type")} value={row.id_type} options={[{ value: "cccd", label: "CCCD" }, { value: "cmnd", label: "CMND" }, { value: "passport", label: "Hộ chiếu" }, { value: "birth_certificate", label: "Giấy khai sinh" }]} onChange={value => sua(index, "id_type", value)} />
                  </Form.Item>
                  <Form.Item htmlFor={inputId(index, "identity")} label="Số giấy tờ" className="xl:col-span-2">
                    <Input id={inputId(index, "identity")} value={row.identity_number} maxLength={50} autoComplete="off" onChange={event => sua(index, "identity_number", event.target.value)} />
                  </Form.Item>
                </>}
                {row.type !== "infant" && <Form.Item htmlFor={inputId(index, "phone")} label="Điện thoại" className={row.type === "child" ? "sm:col-span-2" : ""}>
                  <Input id={inputId(index, "phone")} type="tel" value={row.phone} maxLength={20} onChange={event => sua(index, "phone", event.target.value)} />
                </Form.Item>}
                <Form.Item htmlFor={inputId(index, "request")} label={<span className="inline-flex items-center">Yêu cầu riêng<FieldHelp label="Thông tin yêu cầu riêng">Ăn chay, dị ứng thực phẩm hoặc cần hỗ trợ di chuyển. Có thể để trống.</FieldHelp></span>} className="sm:col-span-2 xl:col-span-4">
                  <Input id={inputId(index, "request")} value={row.special_request} maxLength={500} placeholder="Nếu có" onChange={event => sua(index, "special_request", event.target.value)} />
                </Form.Item>
              </div>
              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <div className="flex items-center gap-1"><Radio name="passenger-contact" checked={row.is_contact} onChange={() => sua(index, "is_contact", true)}>Người đại diện</Radio><FieldHelp label="Vai trò người đại diện">Người liên hệ chính của nhóm. Chỉ chọn một người trong danh sách.</FieldHelp></div>
                {index < rows.length - 1 && <Button type="text" onClick={() => setActivePassenger(String(index + 1))} disabled={false}>Hành khách tiếp theo →</Button>}
              </div>
            </Form>,
          }))} />

          {data.warnings.length > 0 && data.can_edit && <details className="mt-5 text-sm text-slate-600">
            <summary className="cursor-pointer font-medium">Hồ sơ đã lưu có {data.warnings.length} lưu ý</summary>
            <ul className="mt-3 list-disc space-y-2 pl-5">{data.warnings.map(warning => <li key={warning}>{warning}</li>)}</ul>
          </details>}
          {error && <div className="mt-5" role="alert"><Alert type="error" showIcon title={error} /></div>}

          {data.can_edit && <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white py-4 lg:sticky lg:bottom-0 lg:z-10">
            <p role="status" aria-live="polite" className={`text-sm ${saved ? "text-teal-700" : "text-slate-500"}`}>
              {saving ? "Đang lưu..." : saved ? "Đã lưu danh sách" : imported ? "Đã nhập từ file · Chưa lưu" : dirty ? "Có thay đổi chưa lưu" : `${namedCount}/${data.guests} người đã có tên`}
            </p>
            <Tooltip title={!identityVerified ? "Xác thực OTP qua email để lưu danh sách" : undefined}>
              <span><Button type="primary" loading={saving} disabled={!identityVerified || namedCount === 0} onClick={luu}>Lưu danh sách</Button></span>
            </Tooltip>
          </div>}
        </section>
      </div>
    </div>
  );
}
