import adminService from "@/services/adminService";
import type {
  SandboxBookingRow,
  SandboxOptions,
  SandboxTour,
} from "@/services/adminService";
import { formatPrice } from "@/utils/format";
import type { AxiosError } from "axios";
import { useEffect, useMemo, useState } from "react";

/**
 * Sân thử nghiệm nghiệp vụ.
 *
 * ## Màn này giải quyết chuyện gì
 *
 * Gần như mọi luật tiền bạc treo vào một mốc tính lùi từ ngày khởi hành, nên muốn xem hệ thống xử
 * lý một tình huống ra sao thì phải chờ tới đúng ngày — với hạn trả nốt là chờ hàng tuần. Không ai
 * chứng minh được một quy trình mười ngày trong một buổi ngồi trước máy.
 *
 * Nút ở đây **không vẽ ra kết quả mong muốn**. Chúng kéo ngày khởi hành tới đúng khoảng cách mà một
 * mốc cần, rồi gọi chính lệnh nền chạy hằng đêm. Thứ hiện ra sau đó là hành vi thật trên dữ liệu
 * thật.
 *
 * ## Bảng đơn là phần quan trọng nhất
 *
 * Không có nó thì mỗi lần bấm chỉ đổi lại một dòng thông báo, và người xem phải tin lời. Bảng giữ
 * nguyên tại chỗ giữa các lần bấm để so được trước và sau: dòng nào đổi trạng thái, số nào nhúc
 * nhích, cột thư nào vừa được đóng dấu.
 */
const SandboxLab = () => {
  const [tours, setTours] = useState<SandboxTour[]>([]);
  const [options, setOptions] = useState<SandboxOptions | null>(null);
  const [scheduleId, setScheduleId] = useState<number | null>(null);
  const [rows, setRows] = useState<SandboxBookingRow[]>([]);
  const [scheduleInfo, setScheduleInfo] = useState<string>("");
  const [busy, setBusy] = useState("");
  const [log, setLog] = useState<{ kind: "ok" | "err"; text: string }[]>([]);
  const [mailType, setMailType] = useState("balance_reminder");

  const [dichId, setDichId] = useState<number | null>(null);
  const [donId, setDonId] = useState<number | null>(null);

  const chonChuyen = useMemo(
    () =>
      tours.flatMap((t) =>
        t.schedules.map((s) => ({ tour: t.title, tourId: t.id, ...s })),
      ),
    [tours],
  );

  /*
   * Chuyến đích chỉ lấy trong CÙNG TOUR.
   *
   * Ghép chuyến bắt buộc cùng tour — ghép hai tour khác nhau là đổi hẳn sản phẩm khách đã mua. Lọc
   * sẵn ở đây để danh sách không mời người bấm chọn một thứ máy chủ chắc chắn từ chối.
   */
  const cungTour = useMemo(() => {
    const hienTai = chonChuyen.find((s) => s.id === scheduleId);
    if (!hienTai) return [];
    return chonChuyen.filter((s) => s.tourId === hienTai.tourId && s.id !== hienTai.id);
  }, [chonChuyen, scheduleId]);

  const ghiLog = (kind: "ok" | "err", text: string) =>
    setLog((cu) => [{ kind, text }, ...cu].slice(0, 12));

  const loi = (e: unknown) =>
    (e as AxiosError<{ message?: string }>)?.response?.data?.message
    ?? "Thao tác không thành công.";

  const taiBang = async (id: number) => {
    try {
      const data = await adminService.getSandboxSnapshot(id);
      setRows(data.bookings);
      setScheduleInfo(
        `Khởi hành ${data.schedule.start_date ?? "—"} · hạn chốt ${data.schedule.booking_deadline ?? "—"} · ${data.schedule.status}`,
      );
    } catch (e) {
      ghiLog("err", loi(e));
    }
  };

  /*
   * Tải bảng ngay trong cùng chuỗi bất đồng bộ, không tách ra một effect nghe ngóng `scheduleId`.
   *
   * Effect nghe biến trạng thái rồi lại gọi setState là kiểu đổ thác: mỗi lần chọn chuyến sinh hai
   * lượt vẽ lại, và React cảnh báo đúng chuyện đó. Chọn chuyến là một hành động của người dùng —
   * việc tải bảng thuộc về chính hành động ấy.
   */
  const chonVaTai = (id: number | null) => {
    setScheduleId(id);
    if (id) void taiBang(id);
  };

  useEffect(() => {
    Promise.all([adminService.getSandboxTours(), adminService.getSandboxOptions()])
      .then(([ds, opt]) => {
        setTours(ds);
        setOptions(opt);
        const dau = ds[0]?.schedules[0]?.id ?? null;
        if (dau) chonVaTai(dau);
      })
      .catch(() => ghiLog("err", "Không tải được dữ liệu sân thử."));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const tua = async (moc: string, nhan: string) => {
    if (!scheduleId) return;
    setBusy(moc);
    try {
      const kq = await adminService.sandboxFastForward(scheduleId, moc);
      setRows(kq.bookings);
      ghiLog("ok", kq.message);
      await taiBang(scheduleId);
    } catch (e) {
      ghiLog("err", `${nhan}: ${loi(e)}`);
    } finally {
      setBusy("");
    }
  };

  const chayLenh = async (lenh: string, nhan: string) => {
    setBusy(lenh);
    try {
      const kq = await adminService.sandboxRunCommand(lenh, scheduleId ?? undefined);
      if (kq.bookings) setRows(kq.bookings);
      ghiLog("ok", `${kq.message}\n${kq.output}`);
    } catch (e) {
      ghiLog("err", `${nhan}: ${loi(e)}`);
    } finally {
      setBusy("");
    }
  };

  const ghep = async () => {
    if (!scheduleId || !dichId) return;
    setBusy("merge");
    try {
      const kq = await adminService.sandboxMerge(scheduleId, dichId);
      setRows(kq.bookings);
      ghiLog("ok", kq.message);
      // Chuyến nguồn vừa bị đóng và khách dồn sang đích — chuyển bảng theo để đọc tiếp cho đúng chỗ.
      chonVaTai(dichId);
    } catch (e) {
      ghiLog("err", loi(e));
    } finally {
      setBusy("");
    }
  };

  const chuyen = async (aiYeuCau: "customer" | "company") => {
    if (!donId || !dichId) return;
    setBusy(`transfer-${aiYeuCau}`);
    try {
      const kq = await adminService.sandboxTransfer(donId, dichId, aiYeuCau);
      setRows(kq.bookings);
      ghiLog("ok", kq.message);
      chonVaTai(dichId);
    } catch (e) {
      ghiLog("err", loi(e));
    } finally {
      setBusy("");
    }
  };

  const guiThu = async (bookingId: number) => {
    setBusy(`mail-${bookingId}`);
    try {
      ghiLog("ok", await adminService.sendBookingMail(bookingId, mailType));
    } catch (e) {
      ghiLog("err", loi(e));
    } finally {
      setBusy("");
    }
  };

  const nut =
    "px-3 py-2 text-xs font-semibold rounded-xl border transition-colors disabled:opacity-40";

  return (
    <div className="space-y-6">
      <header className="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
        <h1 className="text-lg font-bold text-amber-900">Sân thử nghiệm nghiệp vụ</h1>
        <p className="mt-1 max-w-3xl text-sm leading-relaxed text-amber-800">
          Các nút dưới đây kéo ngày khởi hành của chuyến tới đúng mốc cần xem, rồi chạy{" "}
          <b>chính lệnh nền chạy hằng đêm</b> — không phải bản giả lập. Chỉ dùng được trên tour
          đánh dấu sân thử; trên tour thật, dời ngày khởi hành của chuyến đã có khách là dời hạn
          thanh toán của từng người và hệ thống chặn.
        </p>
      </header>

      <div className="rounded-2xl border border-gray-200 bg-white p-5">
        <label className="block text-xs font-bold uppercase tracking-wider text-gray-500">
          Chuyến đang thao tác
        </label>
        <select
          value={scheduleId ?? ""}
          onChange={(e) => chonVaTai(Number(e.target.value) || null)}
          className="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20"
        >
          {chonChuyen.length === 0 && <option value="">Chưa có tour sân thử nào</option>}
          {chonChuyen.map((s) => (
            <option key={s.id} value={s.id}>
              #{s.id} · {s.tour} · {s.start_date} · {s.booked_people}/{s.max_people} chỗ
            </option>
          ))}
        </select>
        {scheduleInfo && <p className="mt-2 text-xs text-gray-500">{scheduleInfo}</p>}
        {chonChuyen.length === 0 && (
          <p className="mt-2 text-xs text-rose-600">
            Chạy <code>php artisan db:seed --class=SandboxTourSeeder</code> để dựng dữ liệu.
          </p>
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-2xl border border-gray-200 bg-white p-5">
          <h2 className="text-sm font-bold text-gray-900">1 · Tua thời gian</h2>
          <p className="mt-1 text-xs text-gray-500">
            Dời ngày khởi hành sao cho hôm nay rơi đúng vào mốc. Chỉ đổi ngày, không đụng tới tiền.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            {Object.entries(options?.milestones ?? {}).map(([key, nhan]) => (
              <button
                key={key}
                type="button"
                disabled={!scheduleId || busy !== ""}
                onClick={() => void tua(key, nhan)}
                className={`${nut} border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100`}
              >
                {busy === key ? "Đang tua..." : nhan}
              </button>
            ))}
          </div>
        </section>

        <section className="rounded-2xl border border-gray-200 bg-white p-5">
          <h2 className="text-sm font-bold text-gray-900">2 · Chạy lệnh nền</h2>
          <p className="mt-1 text-xs text-gray-500">
            Đúng lệnh máy chủ hẹn giờ chạy mỗi sáng. Đầu ra in nguyên văn ở nhật ký bên dưới.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            {Object.entries(options?.commands ?? {}).map(([key, nhan]) => (
              <button
                key={key}
                type="button"
                disabled={busy !== ""}
                onClick={() => void chayLenh(key, nhan)}
                className={`${nut} border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100`}
              >
                {busy === key ? "Đang chạy..." : nhan}
              </button>
            ))}
          </div>
        </section>
      </div>

      <section className="rounded-2xl border border-gray-200 bg-white p-5">
        <h2 className="text-sm font-bold text-gray-900">3 · Ghép và chuyển chuyến</h2>
        <p className="mt-1 text-xs text-gray-500">
          Hai đường duy nhất đổi được ngày khởi hành của một đơn đã tồn tại — và vì hạn trả nốt suy
          ra từ ngày khởi hành, đổi ngày là đổi hạn của từng người trên chuyến.
        </p>

        <div className="mt-3 grid gap-3 md:grid-cols-2">
          <div className="rounded-xl border border-gray-200 p-3.5">
            <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500">
              Chuyến đích (cùng tour)
            </label>
            <select
              value={dichId ?? ""}
              onChange={(e) => setDichId(Number(e.target.value) || null)}
              className="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-xs"
            >
              <option value="">— chọn chuyến —</option>
              {cungTour.map((s) => (
                <option key={s.id} value={s.id}>
                  #{s.id} · {s.start_date} · {s.booked_people}/{s.max_people} chỗ
                </option>
              ))}
            </select>
            <button
              type="button"
              disabled={!dichId || busy !== ""}
              onClick={() => void ghep()}
              className={`${nut} mt-2.5 w-full border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100`}
            >
              {busy === "merge" ? "Đang ghép..." : "Ghép cả chuyến này vào chuyến đích"}
            </button>
            <p className="mt-2 text-[11px] leading-relaxed text-gray-500">
              Luôn là công ty khởi xướng, không hỏi khách — nên khách được hoàn đủ nếu sau đó hủy.
              Đơn chưa cọc sẽ bị hủy và mời đặt lại.
            </p>
          </div>

          <div className="rounded-xl border border-gray-200 p-3.5">
            <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500">
              Chuyển một đơn sang chuyến đích
            </label>
            <select
              value={donId ?? ""}
              onChange={(e) => setDonId(Number(e.target.value) || null)}
              className="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-xs"
            >
              <option value="">— chọn đơn —</option>
              {rows.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.ma} · {r.trang_thai} · còn thiếu {formatPrice(r.con_thieu)}
                </option>
              ))}
            </select>
            <div className="mt-2.5 grid grid-cols-2 gap-2">
              <button
                type="button"
                disabled={!donId || !dichId || busy !== ""}
                onClick={() => void chuyen("company")}
                className={`${nut} border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100`}
              >
                {busy === "transfer-company" ? "..." : "Công ty dời ngày"}
              </button>
              <button
                type="button"
                disabled={!donId || !dichId || busy !== ""}
                onClick={() => void chuyen("customer")}
                className={`${nut} border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100`}
              >
                {busy === "transfer-customer" ? "..." : "Khách xin đổi"}
              </button>
            </div>
            <p className="mt-2 text-[11px] leading-relaxed text-gray-500">
              Cùng một thao tác, hai kết cục. <b>Công ty dời</b>: miễn phí đổi lịch, hủy sau đó hoàn
              đủ. <b>Khách xin</b>: từ lần hai có phí, và bảng phí hủy áp bình thường. Bấm cả hai
              rồi so bảng.
            </p>
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-gray-200 bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-sm font-bold text-gray-900">4 · Đơn của chuyến</h2>
            <p className="mt-1 text-xs text-gray-500">
              So bảng này trước và sau mỗi lần bấm — đó là bằng chứng, không phải lời hứa.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <select
              value={mailType}
              onChange={(e) => setMailType(e.target.value)}
              className="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs"
            >
              {Object.entries(options?.mails ?? {}).map(([key, nhan]) => (
                <option key={key} value={key}>
                  {nhan}
                </option>
              ))}
            </select>
            <span className="text-xs text-gray-400">← chọn rồi bấm "Gửi" ở từng dòng</span>
          </div>
        </div>

        <div className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[980px] text-xs">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500">
                <th className="py-2 pr-3">Đơn</th>
                <th className="py-2 pr-3">Trạng thái</th>
                <th className="py-2 pr-3 text-right">Giá đơn</th>
                <th className="py-2 pr-3 text-right">Đã thu</th>
                <th className="py-2 pr-3 text-right">Còn thiếu</th>
                <th className="py-2 pr-3 text-right">Trả lần này</th>
                <th className="py-2 pr-3 text-right">Phải hoàn</th>
                <th className="py-2 pr-3">Hạn trả nốt</th>
                <th className="py-2 pr-3">Thư đã gửi</th>
                <th className="py-2 pr-3">Chỗ</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody className="tabular-nums">
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-gray-100">
                  <td className="py-2 pr-3 font-semibold text-gray-900">
                    {r.ma}
                    {r.la_doan && (
                      <span className="ml-1.5 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">
                        ĐOÀN
                      </span>
                    )}
                  </td>
                  <td className="py-2 pr-3">
                    <span
                      className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        r.trang_thai === "cancelled"
                          ? "bg-rose-50 text-rose-700"
                          : r.trang_thai === "pending"
                            ? "bg-amber-50 text-amber-700"
                            : "bg-emerald-50 text-emerald-700"
                      }`}
                    >
                      {r.trang_thai}
                    </span>
                  </td>
                  <td className="py-2 pr-3 text-right">{formatPrice(r.tong_don)}</td>
                  <td className="py-2 pr-3 text-right text-emerald-700">{formatPrice(r.da_thu)}</td>
                  <td className="py-2 pr-3 text-right font-semibold">
                    {r.con_thieu > 0 ? formatPrice(r.con_thieu) : "—"}
                  </td>
                  <td className="py-2 pr-3 text-right">
                    {r.phai_tra_lan_nay > 0 ? formatPrice(r.phai_tra_lan_nay) : "—"}
                  </td>
                  <td className="py-2 pr-3 text-right text-rose-700">
                    {r.nghia_vu_hoan > 0 ? formatPrice(r.nghia_vu_hoan) : "—"}
                  </td>
                  <td className="py-2 pr-3">{r.han_tra_not ?? "—"}</td>
                  <td className="py-2 pr-3">
                    {r.da_nhac || r.da_canh_bao_cuoi ? (
                      <span>
                        {r.da_nhac && <span className="text-teal-700">nhẹ {r.da_nhac}</span>}
                        {r.da_nhac && r.da_canh_bao_cuoi && " · "}
                        {r.da_canh_bao_cuoi && (
                          <span className="font-semibold text-rose-700">
                            cuối {r.da_canh_bao_cuoi}
                          </span>
                        )}
                      </span>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="py-2 pr-3">{r.cho_da_tra ? "đã trả" : "đang giữ"}</td>
                  <td className="py-2 text-right">
                    <button
                      type="button"
                      disabled={busy !== ""}
                      onClick={() => void guiThu(r.id)}
                      className="rounded-lg border border-gray-200 px-2.5 py-1 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                    >
                      {busy === `mail-${r.id}` ? "..." : "Gửi thư"}
                    </button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={11} className="py-6 text-center text-gray-400">
                    Chưa có đơn nào trên chuyến này.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-2xl border border-gray-200 bg-white p-5">
        <h2 className="text-sm font-bold text-gray-900">Nhật ký thao tác</h2>
        <div className="mt-3 space-y-2">
          {log.length === 0 && (
            <p className="text-xs text-gray-400">Chưa bấm gì. Kết quả từng thao tác sẽ hiện ở đây.</p>
          )}
          {log.map((d, i) => (
            <pre
              key={i}
              className={`whitespace-pre-wrap rounded-xl border px-3.5 py-2.5 text-xs leading-relaxed ${
                d.kind === "ok"
                  ? "border-emerald-200 bg-emerald-50 text-emerald-900"
                  : "border-rose-200 bg-rose-50 text-rose-800"
              }`}
            >
              {d.text}
            </pre>
          ))}
        </div>
      </section>
    </div>
  );
};

export default SandboxLab;
