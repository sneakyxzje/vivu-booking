import adminService from "@/services/adminService";
import type {
  SandboxRunResult,
  SandboxScenarioBooking,
  SandboxScenarioInfo,
} from "@/services/adminService";
import { formatPrice } from "@/utils/format";
import type { AxiosError } from "axios";
import { useEffect, useMemo, useState } from "react";

/**
 * Sân thử nghiệm nghiệp vụ — chạy theo KỊCH BẢN.
 *
 * ## Vì sao không còn là bảng nút
 *
 * Bản đầu là một loạt nút rời: tua tới mốc này, chạy lệnh kia, rồi tự đọc bảng mà kết luận. Đúng về
 * kỹ thuật và gần như vô dụng khi cần **chứng minh** điều gì — người xem phải thuộc sẵn luồng mới
 * biết bấm gì trước bấm gì sau, và nhìn cột nào để biết đúng hay sai.
 *
 * Ở đây mỗi tình huống là một kịch bản có tên. Bấm một lần, máy chủ dựng dữ liệu riêng của nó, chạy
 * các bước theo đúng thứ tự đời thật, rồi trả về biên bản đã tự chấm từng bước.
 *
 * ## Ba thứ luôn hiện cạnh nhau
 *
 * Biên bản nói **chuyện gì xảy ra**, sổ giao dịch nói **tiền đi đâu**, nhật ký nói **hệ thống ghi
 * lại thế nào**. Thiếu một trong ba thì người xem phải tin lời thay vì đối chiếu — và câu hay bị
 * vặn nhất về mô hình đặt cọc không phải "đơn có bị hủy không" mà là "vậy tiền của khách đi đâu".
 */
const SandboxLab = () => {
  const [danhMuc, setDanhMuc] = useState<SandboxScenarioInfo[]>([]);
  const [dangChon, setDangChon] = useState<string | null>(null);
  const [bienBan, setBienBan] = useState<SandboxRunResult | null>(null);
  const [dangChay, setDangChay] = useState(false);
  const [loi, setLoi] = useState("");

  const theoNhom = useMemo(() => {
    const map = new Map<string, SandboxScenarioInfo[]>();
    danhMuc.forEach((kb) => {
      map.set(kb.nhom, [...(map.get(kb.nhom) ?? []), kb]);
    });
    return [...map.entries()];
  }, [danhMuc]);

  useEffect(() => {
    adminService
      .getSandboxScenarios()
      .then(setDanhMuc)
      .catch(() => setLoi("Không tải được danh mục kịch bản."));
  }, []);

  const chay = async (id: string) => {
    setDangChon(id);
    setDangChay(true);
    setLoi("");
    setBienBan(null);

    try {
      setBienBan(await adminService.runSandboxScenario(id));
    } catch (e) {
      setLoi(
        (e as AxiosError<{ message?: string }>)?.response?.data?.message
          ?? "Kịch bản chạy lỗi.",
      );
    } finally {
      setDangChay(false);
    }
  };

  return (
    <div className="space-y-5">
      <header className="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
        <h1 className="text-lg font-bold text-amber-900">Sân thử nghiệm nghiệp vụ</h1>
        <p className="mt-1 max-w-4xl text-sm leading-relaxed text-amber-800">
          Mỗi kịch bản tự dựng dữ liệu của nó, chạy các bước theo đúng thứ tự đời thật, rồi tự chấm
          từng bước. <b>Không có bước nào được giả lập</b> — mọi thao tác gọi đúng dịch vụ và đúng
          lệnh nền mà đường thật đi qua. Thứ duy nhất bị can thiệp là ngày khởi hành của chuyến, và
          đó là cách kéo đồng hồ tới nơi thay vì chờ mười ngày.
        </p>
      </header>

      <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
        {/* ── Cột trái: danh mục kịch bản ─────────────────────────────────────── */}
        <aside className="space-y-4">
          {theoNhom.map(([nhom, ds]) => (
            <div key={nhom} className="rounded-2xl border border-gray-200 bg-white p-3">
              <p className="px-1.5 pb-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                {nhom}
              </p>
              <div className="space-y-1">
                {ds.map((kb) => {
                  const chon = dangChon === kb.id;
                  return (
                    <button
                      key={kb.id}
                      type="button"
                      disabled={dangChay}
                      onClick={() => void chay(kb.id)}
                      className={`w-full rounded-xl px-3 py-2.5 text-left transition-colors disabled:opacity-50 ${
                        chon
                          ? "bg-primary-50 ring-1 ring-primary-200"
                          : "hover:bg-gray-50"
                      }`}
                    >
                      <span
                        className={`block text-xs font-bold ${chon ? "text-primary-800" : "text-gray-800"}`}
                      >
                        {kb.ten}
                      </span>
                      <span className="mt-0.5 block text-[11px] leading-relaxed text-gray-500">
                        {kb.chung_minh}
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </aside>

        {/* ── Cột phải: biên bản ──────────────────────────────────────────────── */}
        <section className="space-y-4">
          {loi && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
              {loi}
            </div>
          )}

          {dangChay && (
            <div className="rounded-2xl border border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500">
              Đang dựng dữ liệu và chạy các bước…
            </div>
          )}

          {!dangChay && !bienBan && !loi && (
            <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
              <p className="text-sm font-semibold text-gray-700">
                Chọn một kịch bản ở cột bên trái
              </p>
              <p className="mx-auto mt-2 max-w-md text-xs leading-relaxed text-gray-500">
                Mỗi lần chạy đều dựng lại dữ liệu từ đầu, nên bấm đi bấm lại bao nhiêu lần cũng ra
                cùng kết quả và không ăn vào kịch bản khác.
              </p>
            </div>
          )}

          {!dangChay && bienBan && <BienBan bb={bienBan} />}
        </section>
      </div>
    </div>
  );
};

// ── Biên bản một lần chạy ─────────────────────────────────────────────────────

const BienBan = ({ bb }: { bb: SandboxRunResult }) => (
  <>
    <div
      className={`rounded-2xl border px-5 py-4 ${
        bb.dat ? "border-emerald-200 bg-emerald-50" : "border-rose-200 bg-rose-50"
      }`}
    >
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500">{bb.nhom}</p>
          <h2 className="mt-0.5 text-base font-bold text-gray-900">{bb.ten}</h2>
          <p className="mt-1 text-xs leading-relaxed text-gray-600">{bb.chung_minh}</p>
        </div>
        <span
          className={`shrink-0 rounded-full px-3 py-1 text-xs font-bold ${
            bb.dat ? "bg-emerald-600 text-white" : "bg-rose-600 text-white"
          }`}
        >
          {bb.dat ? "ĐẠT" : "CÓ BƯỚC HỎNG"}
        </span>
      </div>
    </div>

    <ol className="space-y-2">
      {bb.buoc.map((b) => (
        <li
          key={b.thu_tu}
          className={`rounded-2xl border bg-white p-4 ${
            b.dat ? "border-gray-200" : "border-rose-300 ring-1 ring-rose-100"
          }`}
        >
          <div className="flex items-start gap-3">
            <span
              className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${
                b.dat ? "bg-emerald-100 text-emerald-700" : "bg-rose-100 text-rose-700"
              }`}
            >
              {b.dat ? "✓" : "✗"}
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-bold text-gray-900">
                Bước {b.thu_tu} · {b.lam_gi}
              </p>
              <dl className="mt-2 grid gap-1.5 text-xs sm:grid-cols-[80px_1fr]">
                <dt className="font-semibold text-gray-500">Kỳ vọng</dt>
                <dd className="leading-relaxed text-gray-700">{b.ky_vong}</dd>
                <dt className="font-semibold text-gray-500">Kết quả</dt>
                <dd
                  className={`leading-relaxed font-semibold ${
                    b.dat ? "text-emerald-800" : "text-rose-700"
                  }`}
                >
                  {b.ket_qua}
                </dd>
              </dl>
            </div>
          </div>
        </li>
      ))}
    </ol>

    {bb.don.map((don) => (
      <ThongTinDon key={don.ma} don={don} />
    ))}
  </>
);

// ── Sổ giao dịch và nhật ký của một đơn ───────────────────────────────────────

const LOAI_THU: Array<[string, string]> = [
  ["created", "Xác nhận đã nhận đơn"],
  ["confirmed", "Chỗ đã được giữ"],
  ["paid", "Đã nhận thanh toán"],
  ["balance_reminder", "Nhắc trả nốt — lá nhẹ"],
  ["balance_final", "Nhắc trả nốt — cảnh báo cuối"],
  ["departure", "Nhắc trước ngày đi"],
  ["cancelled", "Báo đơn đã hủy"],
];

const ThongTinDon = ({ don }: { don: SandboxScenarioBooking }) => {
  const [loaiThu, setLoaiThu] = useState("balance_reminder");
  const [dangGui, setDangGui] = useState(false);
  const [ketQuaThu, setKetQuaThu] = useState<{ ok: boolean; text: string } | null>(null);

  const guiThu = async () => {
    setDangGui(true);
    setKetQuaThu(null);

    try {
      setKetQuaThu({ ok: true, text: await adminService.sendBookingMail(don.id, loaiThu) });
    } catch (e) {
      setKetQuaThu({
        ok: false,
        text:
          (e as AxiosError<{ message?: string }>)?.response?.data?.message
          ?? "Không gửi được thư.",
      });
    } finally {
      setDangGui(false);
    }
  };

  return (
  <div className="rounded-2xl border border-gray-200 bg-white p-5">
    <div className="flex flex-wrap items-center gap-3">
      <span className="rounded-lg bg-gray-900 px-2.5 py-1 text-xs font-bold text-white">
        {don.ma}
      </span>
      <span
        className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold ${
          don.trang_thai === "cancelled"
            ? "bg-rose-50 text-rose-700"
            : don.trang_thai === "pending"
              ? "bg-amber-50 text-amber-700"
              : "bg-emerald-50 text-emerald-700"
        }`}
      >
        {don.trang_thai}
      </span>
      {don.han_tra_not && (
        <span className="text-xs text-gray-500">Hạn trả nốt {don.han_tra_not}</span>
      )}
      <span className="text-xs text-gray-500">
        Chỗ {don.cho_da_tra ? "đã về kho" : "đang giữ"}
      </span>
    </div>

    <div className="mt-4 grid gap-3 sm:grid-cols-4">
      {[
        ["Giá trị đơn", don.tong_don, "text-gray-900"],
        ["Đã thu", don.da_thu, "text-emerald-700"],
        ["Còn thiếu", don.con_thieu, "text-amber-700"],
        ["Phải hoàn khách", don.nghia_vu_hoan, "text-rose-700"],
      ].map(([nhan, so, mau]) => (
        <div key={nhan as string} className="rounded-xl bg-gray-50 px-3.5 py-2.5">
          <p className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">{nhan}</p>
          <p className={`mt-0.5 text-sm font-bold tabular-nums ${mau}`}>
            {formatPrice(Number(so))}
          </p>
        </div>
      ))}
    </div>

    {/*
      Sổ giao dịch với dấu cộng trừ rõ ràng.

      Đây là bảng trả lời nhanh nhất cho câu "vậy tiền của khách đi đâu" — câu hay bị vặn nhất về
      mô hình đặt cọc, và là câu mà mọi đoạn giải thích bằng chữ đều thua một cột số.
    */}
    <div className="mt-4">
      <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Sổ giao dịch</p>
      {don.so_giao_dich.length === 0 ? (
        <p className="mt-2 rounded-lg bg-gray-50 px-3.5 py-2.5 text-xs text-gray-500">
          Chưa có bút toán nào.
        </p>
      ) : (
        <div className="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-100">
          {don.so_giao_dich.map((d, i) => (
            <div key={i} className="flex items-center gap-3 px-3.5 py-2.5 text-xs">
              <span
                className={`flex h-6 w-6 items-center justify-center rounded-full text-sm font-bold ${
                  d.chieu === "+"
                    ? "bg-emerald-50 text-emerald-700"
                    : "bg-rose-50 text-rose-700"
                }`}
              >
                {d.chieu}
              </span>
              <span className="font-semibold text-gray-800">{d.nhan}</span>
              <span
                className={`ml-auto font-bold tabular-nums ${
                  d.chieu === "+" ? "text-emerald-700" : "text-rose-700"
                }`}
              >
                {d.chieu}
                {formatPrice(d.so_tien)}
              </span>
              {d.luc && <span className="w-28 text-right text-gray-400">{d.luc}</span>}
            </div>
          ))}
        </div>
      )}
    </div>

    {don.nhat_ky.length > 0 && (
      <div className="mt-4">
        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Nhật ký đơn</p>
        <div className="mt-2 space-y-1.5">
          {don.nhat_ky.map((n, i) => (
            <div key={i} className="rounded-lg bg-gray-50 px-3.5 py-2 text-xs">
              <span className="font-semibold text-gray-800">{n.hanh_dong}</span>
              {n.tu && n.sang && (
                <span className="ml-2 text-gray-500">
                  {n.tu} → <b className="text-gray-700">{n.sang}</b>
                </span>
              )}
              {n.luc && <span className="ml-2 text-gray-400">{n.luc}</span>}
              {n.ly_do && <p className="mt-0.5 leading-relaxed text-gray-500">{n.ly_do}</p>}
            </div>
          ))}
        </div>
      </div>
    )}

    {/*
      Gửi lại một lá thư của chính đơn này.

      Đặt ở đây chứ không phải một màn riêng: lá thư nói về đơn này, và cả những lời từ chối cũng
      vậy — thư nhắc trả nốt bị chối trên đơn không còn nợ, thư báo hủy bị chối trên đơn còn hiệu
      lực. Một lá thư sai hoàn cảnh tệ hơn không có thư, vì nó đến từ công ty và khách tin nó.
    */}
    <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5">
      <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Gửi lại thư</p>
      <div className="mt-2 flex flex-wrap items-center gap-2">
        <select
          value={loaiThu}
          onChange={(e) => setLoaiThu(e.target.value)}
          className="min-w-[240px] flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"
        >
          {LOAI_THU.map(([gt, nhan]) => (
            <option key={gt} value={gt}>
              {nhan}
            </option>
          ))}
        </select>
        <button
          type="button"
          disabled={dangGui}
          onClick={() => void guiThu()}
          className="rounded-lg border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-bold text-primary-700 transition-colors hover:bg-primary-100 disabled:opacity-50"
        >
          {dangGui ? "Đang gửi…" : "Gửi ngay"}
        </button>
      </div>
      {ketQuaThu && (
        <p
          className={`mt-2 rounded-lg px-3 py-2 text-xs leading-relaxed ${
            ketQuaThu.ok
              ? "bg-emerald-50 text-emerald-800"
              : "bg-rose-50 text-rose-700"
          }`}
        >
          {ketQuaThu.text}
        </p>
      )}
    </div>
  </div>
  );
};

export default SandboxLab;
