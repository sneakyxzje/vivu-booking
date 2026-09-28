import {
  Button as AntButton,
  Flex as UIFlex,
  Input as AntInput,
  Modal as AntModal,
  Select as AntSelect,
  Typography as AntTypography,
} from "antd";
import { useAdminPrompt } from "@/components/admin/useAdminPrompt";


import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, Clock, User } from "lucide-react";
import adminService from "@/services/adminService";
import type {
  AdminIncident,
  IncidentChargeInput,
  IncidentDetailResponse,
  IncidentListResponse,
} from "@/services/adminService";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * O - Điều hành xử lý sự cố và phân bổ chi phí.
 *
 * Đây là nơi duy nhất quyết được tiền của một sự cố. Hướng dẫn viên báo cáo ở màn riêng và không
 * có đường nào chạm tới các ô ở đây — đó là chủ ý, không phải giới hạn kỹ thuật.
 *
 * Nguyên tắc phân bổ (tài liệu 04 mục 6.3): **hãng chịu chi phí thuộc nghĩa vụ tổ chức, khách
 * chịu chi phí thuộc tiêu dùng cá nhân thực tế phát sinh.** Xe hỏng phải thuê xe khác thì hãng
 * chịu; kẹt lại một đêm phải ở thêm phòng thì khách chịu. Cùng một cơn bão sinh ra cả hai loại.
 */

const severityClass: Record<string, string> = {
  low: "bg-gray-100 text-gray-700",
  medium: "bg-amber-50 text-amber-700",
  high: "bg-rose-50 text-rose-700",
};

export default function IncidentManagement() {
  const prompt = useAdminPrompt();
  const [data, setData] = useState<IncidentListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("");

  const [detail, setDetail] = useState<IncidentDetailResponse | null>(null);
  const [resolution, setResolution] = useState("");
  const [costDelta, setCostDelta] = useState("");
  const [whoBears, setWhoBears] = useState("");
  const [charges, setCharges] = useState<IncidentChargeInput[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      setData(await adminService.getIncidents(statusFilter || undefined));
    } catch (err) {
      console.error("Lỗi tải danh sách sự cố:", err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openDetail = async (sc: AdminIncident) => {
    setError("");
    setResolution(sc.resolution ?? "");
    setCostDelta(sc.cost_delta !== null ? String(sc.cost_delta) : "");
    setWhoBears(sc.who_bears ?? "");
    setCharges([]);

    try {
      setDetail(await adminService.getIncident(sc.id));
    } catch (err) {
      console.error("Lỗi tải chi tiết sự cố:", err);
    }
  };

  const bearerHienTai = data?.options.bearers.find((b) => b.value === whoBears);

  /*
   * Người chịu tính theo TỪNG khoản, lùi về mặc định của phương án khi khoản để trống.
   *
   * Trước đây chỉ có một giá trị cho cả sự cố, nên tình huống thật nhất lại là tình huống không
   * nhập được: bão làm tàu không chạy thì chiếc xe thuê thay tàu là hãng chịu, còn đêm phòng ở
   * thêm là khách chịu — hai khoản, hai người, một sự cố.
   */
  const nguoiChiuCuaKhoan = (khoan: IncidentChargeInput) =>
    khoan.who_bears || whoBears || "";

  const khachPhaiTraKhoan = (khoan: IncidentChargeInput) => {
    const value = nguoiChiuCuaKhoan(khoan);
    if (!value) return true;

    return (
      data?.options.bearers.find((b) => b.value === value)?.customer_pays ===
      true
    );
  };

  const themKhoan = () => {
    const dauTien = detail?.bookings[0];
    if (!dauTien) return;

    setCharges((truoc) => [
      ...truoc,
      {
        booking_id: dauTien.booking_id,
        kind: bearerHienTai?.customer_pays === false ? "refund" : "surcharge",
        who_bears: null,
        amount: 0,
        reason: "",
      },
    ]);
  };

  const suaKhoan = (index: number, thayDoi: Partial<IncidentChargeInput>) =>
    setCharges((truoc) =>
      truoc.map((k, i) => (i === index ? { ...k, ...thayDoi } : k)),
    );

  const luuPhuongAn = async () => {
    if (!detail) return;

    setSaving(true);
    setError("");

    try {
      await adminService.resolveIncident(detail.incident.id, {
        resolution: resolution.trim(),
        cost_delta: costDelta ? Number(costDelta) : null,
        who_bears: whoBears || null,
        charges: charges.map((k) => ({ ...k, amount: Number(k.amount) })),
      });

      setDetail(null);
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
      setError(response?.message || "Không lưu được phương án.");
    } finally {
      setSaving(false);
    }
  };

  const duyetKhoan = async (id: number) => {
    await adminService.approveSurcharge(id);
    if (detail) setDetail(await adminService.getIncident(detail.incident.id));
    loadData();
  };

  const mienKhoan = async (id: number) => {
    const lyDo = await prompt("Lý do miễn khoản này (ít nhất 10 ký tự):", "", 10);
    if (!lyDo || lyDo.trim().length < 10) return;

    await adminService.waiveSurcharge(id, lyDo.trim());
    if (detail) setDetail(await adminService.getIncident(detail.incident.id));
    loadData();
  };

  const taiLaiKhoan = async () => {
    if (detail) setDetail(await adminService.getIncident(detail.incident.id));
    loadData();
  };

  /*
   * Khách đồng ý là một sự kiện có thật ở hiện trường, nên ghi riêng chứ không gộp vào lúc thu.
   * Gộp lại thì mất dấu ai nói với khách và lúc nào — đúng thứ cần khi có khiếu nại.
   */
  const ghiNhanDongY = async (id: number) => {
    const ghiChu = await prompt(
      "Ai nói với khách và khách trả lời thế nào? (không bắt buộc)",
      "",
    );

    // Bấm Hủy thì thôi; để trống rồi bấm OK vẫn ghi nhận, vì lời nhắn chỉ là tùy chọn.
    if (ghiChu === null) return;

    await adminService.recordSurchargeConsent(id, ghiChu.trim() || undefined);
    await taiLaiKhoan();
  };

  /** Bước cuối: đẩy tiền vào sổ giao dịch của đơn và đóng khoản lại. */
  const ghiNhanTatToan = async (id: number, laKhoanThu: boolean) => {
    const hinhThuc = await prompt(
      laKhoanThu
        ? "Thu bằng hình thức nào? (VD: tiền mặt, chuyển khoản)"
        : "Hoàn bằng hình thức nào? (VD: tiền mặt, chuyển khoản)",
      "Tiền mặt",
    );

    if (hinhThuc === null) return;

    try {
      await adminService.settleSurcharge(id, {
        method: hinhThuc.trim() || null,
      });
      await taiLaiKhoan();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
      setError(response?.message || "Không ghi nhận được.");
    }
  };

  return (
    <UIFlex vertical gap="large" ><div>
        <AntTypography.Title level={3} >Chi phí phát sinh
        </AntTypography.Title>
        <p className="text-sm text-gray-500 mt-1">
          Hướng dẫn viên báo lại những gì xảy ra với đoàn; ở đây quyết phương án
          và ai trả bao nhiêu. Nguyên tắc: hãng chịu chi phí thuộc nghĩa vụ tổ
          chức, khách chịu chi phí tiêu dùng cá nhân.
        </p>
      </div><UIFlex   wrap align="center"  gap={8}>{[
          { value: "", label: "Tất cả" },
          { value: "reported", label: "Chờ xử lý" },
          { value: "reviewed", label: "Đã có phương án" },
          { value: "resolved", label: "Đã đóng" },
        ].map((item) => (
          <AntButton type={(statusFilter === item.value) ? "primary" : "default"} key={item.value} htmlType="button" onClick={() => setStatusFilter(item.value)}>{item.label}</AntButton>
        ))}</UIFlex><UIFlex vertical gap={12} >{loading && <p className="text-sm text-gray-500">Đang tải...</p>}{!loading && (data?.incidents.length ?? 0) === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Không có sự cố nào.
          </p>
        )}{data?.incidents.map((sc) => (
          <AntButton type={(sc.needs_attention) ? "primary" : "default"} key={sc.id} htmlType="button" onClick={() => openDetail(sc)} style={{ height: "auto", whiteSpace: "normal", textAlign: "left" }}><UIFlex   wrap align="center"  gap={8}><span
                className={`rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${
                  severityClass[sc.severity] ?? severityClass.low
                }`}
              >
                {sc.severity_label}
              </span><span className="text-sm font-bold text-gray-900">
                {sc.type_label}
              </span><span className="text-xs text-gray-500">
                #{sc.tour_schedule_id} · {sc.tour_title}
              </span><span className="ml-auto flex items-center gap-3 text-xs text-gray-500">
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3" />
                  {formatDateTime(sc.occurred_at)}
                </span>
                <span className="rounded bg-gray-100 px-2 py-0.5 font-semibold text-gray-700">
                  {sc.status_label}
                </span>
              </span></UIFlex><p className="mt-1.5 line-clamp-2 text-sm text-gray-700">
              {sc.description}
            </p><p className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-gray-500">
              <span className="flex items-center gap-1">
                <User className="h-3 w-3" />
                {sc.reporter_name ?? "Không rõ"}
              </span>
              {sc.reported_late && (
                <span className="flex items-center gap-1 text-amber-700">
                  <AlertTriangle className="h-3 w-3" />
                  Ghi bù
                </span>
              )}
              {sc.surcharges.length > 0 && (
                <span>
                  {sc.surcharges.length} khoản ·{" "}
                  {sc.surcharges.filter((k) => k.in_effect).length} đã có hiệu
                  lực
                </span>
              )}
            </p></AntButton>
        ))}</UIFlex>{/* Chi tiết và phân bổ chi phí */}{detail && (
        <AntModal open title={<>
                {detail.incident.type_label} — chuyến #
                {detail.incident.tour_schedule_id}
              </>} width={960} onCancel={() => setDetail(null)} closable={!(saving)} keyboard={!(saving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                {detail.incident.reporter_name} báo lúc{" "}
                {formatDateTime(detail.incident.occurred_at)}
                {detail.incident.reported_late ? " (ghi bù)" : ""}
              </p>
            </div>

            <p className="rounded-lg bg-gray-50 p-3 text-sm text-gray-800">
              {detail.incident.description}
            </p>

            {detail.incident.photos.length > 0 && (
              <UIFlex   wrap   gap={8}>{detail.incident.photos.map((anh) => (
                  <img
                    key={anh.id}
                    src={anh.image_path}
                    alt={anh.caption ?? "Ảnh hiện trường"}
                    className="h-20 w-20 rounded-lg border border-gray-200 object-cover"
                  />
                ))}</UIFlex>
            )}

            {/* Khoản đã lập trước đó */}
            {detail.incident.surcharges.length > 0 && (
              <div className="space-y-1.5">
                <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                  Các khoản đã lập
                </p>
                {detail.incident.surcharges.map((kh) => (
                  <div
                    key={kh.id}
                    className="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 p-2.5 text-xs"
                  >
                    <span className="font-bold text-gray-900">
                      BK-{kh.booking_id}
                    </span>
                    <span className="text-gray-600">{kh.customer_name}</span>
                    <span
                      className={`font-semibold ${
                        kh.kind === "refund"
                          ? "text-emerald-700"
                          : "text-rose-700"
                      }`}
                    >
                      {kh.kind_label} {formatPrice(kh.amount)}
                    </span>
                    {kh.who_bears_label && (
                      <span className="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600">
                        {kh.who_bears_label}
                      </span>
                    )}
                    <span className="text-gray-500">{kh.reason}</span>

                    <span className="ml-auto flex flex-wrap items-center gap-2">
                      <span
                        className={`rounded px-2 py-0.5 font-semibold ${
                          kh.settled
                            ? "bg-emerald-600 text-white"
                            : kh.in_effect
                              ? "bg-emerald-50 text-emerald-700"
                              : "bg-gray-100 text-gray-600"
                        }`}
                      >
                        {kh.status_label}
                      </span>

                      {kh.status === "pending" && (
                        <>
                          <AntButton htmlType="button" onClick={() => duyetKhoan(kh.id)}>Duyệt
                          </AntButton>
                          <AntButton htmlType="button" onClick={() => mienKhoan(kh.id)}>Miễn
                          </AntButton>
                        </>
                      )}

                      {/*
                        Vòng đời tiếp tục sau khi duyệt: nói với khách, rồi mới thu. Trước đây
                        khoản duyệt xong là dừng ở đó — sổ giao dịch không bao giờ biết tới số
                        tiền này, và "đã thu" là trạng thái không đường nào đi tới được.
                      */}
                      {kh.status === "approved" && kh.needs_consent && (
                        <AntButton htmlType="button" onClick={() => ghiNhanDongY(kh.id)}>Khách đã đồng ý
                        </AntButton>
                      )}

                      {kh.can_settle && (
                        <AntButton htmlType="button" onClick={() =>
                            ghiNhanTatToan(kh.id, kh.kind === "surcharge")
                          }>{kh.kind === "surcharge"
                            ? "Ghi nhận đã thu"
                            : "Ghi nhận đã hoàn"}</AntButton>
                      )}

                      {kh.status === "approved" && (
                        <AntButton htmlType="button" onClick={() => mienKhoan(kh.id)}>Miễn
                        </AntButton>
                      )}
                    </span>

                    {kh.needs_consent && kh.status === "approved" && (
                      <p className="w-full text-[11px] text-amber-700">
                        Chưa ghi nhận khách đồng ý. Phải nói với khách trước khi
                        thu tiền.
                      </p>
                    )}

                    {kh.consent_note && (
                      <p className="w-full text-[11px] text-gray-400">
                        Khách đồng ý: {kh.consent_note}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            )}

            <div className="border-t border-gray-100 pt-4 space-y-3">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Phương án xử lý <span className="text-rose-500">*</span>
                </label>
                <AntInput.TextArea rows={3} value={resolution} onChange={(e) => setResolution(e.target.value)} placeholder="VD: Đổi sang chương trình tham quan trong bờ, ở thêm một đêm tại khách sạn cũ..." style={{ width: "100%" }} />
                <p className="mt-1 text-[11px] text-gray-400">
                  Hướng dẫn viên sẽ đọc đúng đoạn này cho khách, và đây là căn
                  cứ khi có khiếu nại.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Chênh lệch chi phí (không bắt buộc)
                  </label>
                  <AntInput type="number" value={costDelta} onChange={(e) => setCostDelta(e.target.value)} placeholder="0" style={{ width: "100%" }} />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Ai chịu (mặc định)
                  </label>
                  <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((whoBears) ?? "")} onChange={(e) => setWhoBears(e)} style={{ width: "100%" }} options={[{ value: String(""), label: "Chưa xác định", disabled: false },data?.options.bearers.map((b) => (
                      { value: String(b.value), label: b.label, disabled: false }
                    ))].flat().filter((option) => !!option)} />
                  <p className="mt-1 text-[11px] text-gray-400">
                    Chỉ là giá trị điền sẵn. Từng khoản bên dưới đặt lại được.
                  </p>
                </div>
              </div>

              {bearerHienTai && (
                <p className="rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                  Mặc định <strong>{bearerHienTai.label}</strong>. Một sự cố
                  thường sinh ra nhiều loại khoản — xe thuê thay tàu là nghĩa vụ
                  tổ chức nên hãng chịu, đêm phòng ở thêm là tiêu dùng cá nhân
                  nên khách chịu — nên hãy chỉnh từng dòng cho đúng.
                </p>
              )}

              {/* Phân bổ cho từng đơn */}
              <UIFlex vertical gap={8} ><UIFlex    align="center" justify="space-between" ><p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                    Phân bổ cho từng đơn
                  </p><AntButton htmlType="button" onClick={themKhoan}>+ Thêm khoản
                  </AntButton></UIFlex>{charges.length === 0 && (
                  <p className="text-[11px] text-gray-400">
                    Không lập khoản nào cũng được: sự cố mà hãng chịu toàn bộ
                    thì chỉ cần ghi phương án.
                  </p>
                )}{charges.map((khoan, index) => (
                  <div
                    key={index}
                    className="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 p-2.5 sm:grid-cols-12"
                  >
                    <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((khoan.booking_id) ?? "")} onChange={(e) =>
                        suaKhoan(index, { booking_id: Number(e) })} style={{ width: "100%" }} options={[detail.bookings.map((don) => (
                        { value: String(don.booking_id), label: ["BK-", don.booking_id, "·", don.customer_name].join(" "), disabled: false }
                      ))].flat().filter((option) => !!option)} />

                    {/* Người chịu của riêng dòng này. Để trống thì lấy mặc định của phương án. */}
                    <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(khoan.who_bears ?? "")} onChange={(e) =>
                        suaKhoan(index, { who_bears: e || null })} style={{ width: "100%" }} options={[{ value: String(""), label: bearerHienTai
                          ? `Theo mặc định (${bearerHienTai.label})`
                          : "Chưa xác định", disabled: false },data?.options.bearers.map((b) => (
                        { value: String(b.value), label: b.label, disabled: false }
                      ))].flat().filter((option) => !!option)} />

                    <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((khoan.kind) ?? "")} onChange={(e) =>
                        suaKhoan(index, {
                          kind: e as "surcharge" | "refund",
                        })} style={{ width: "100%" }} options={[data?.options.kinds
                        .filter(
                          (k) =>
                            k.value === "refund" || khachPhaiTraKhoan(khoan),
                        )
                        .map((k) => (
                          { value: String(k.value), label: k.label, disabled: false }
                        ))].flat().filter((option) => !!option)} />

                    <AntInput type="number" value={khoan.amount || ""} onChange={(e) =>
                        suaKhoan(index, { amount: Number(e.target.value) })
                      } placeholder="Số tiền" style={{ width: "100%" }} />

                    <AntButton htmlType="button" onClick={() =>
                        setCharges((truoc) =>
                          truoc.filter((_, i) => i !== index),
                        )
                      } danger>Xóa
                    </AntButton>

                    {/*
                      Diễn giải xuống hàng riêng, chiếm hết chiều ngang. Đây là dòng khách đọc khi
                      được yêu cầu trả thêm, nên nó cần chỗ nhất chứ không phải ít chỗ nhất.
                    */}
                    <AntInput value={khoan.reason} onChange={(e) =>
                        suaKhoan(index, { reason: e.target.value })
                      } placeholder="Diễn giải cho khách — VD: một đêm phòng đôi và hai bữa ăn ngoài lịch trình" style={{ width: "100%" }} />
                  </div>
                ))}</UIFlex>
            </div>

            {error && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {error}
              </div>
            )}

            <UIFlex     justify="end" gap={8}><AntButton htmlType="button" onClick={() => setDetail(null)} disabled={saving}>Đóng
              </AntButton><AntButton htmlType="button" onClick={luuPhuongAn} disabled={saving || resolution.trim().length < 20} type="primary">{saving ? "Đang lưu..." : "Lưu phương án"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}</UIFlex>
  );
}
