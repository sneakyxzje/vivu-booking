import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Select as AntSelect,
  Table as AntTable,
  Typography as AntTypography,
} from "antd";
import { useCallback, useEffect, useState } from "react";
import { Loader2, Lock, Search, Unlock } from "lucide-react";
import adminService from "@/services/adminService";
import type { AdminUser, AdminUserStatus } from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime } from "@/utils/format";

/**
 * Danh sách tài khoản, và cái khóa.
 *
 * Khóa chứ không xóa: `blocked` chặn đăng nhập và thu hồi mọi phiên đang mở, nhưng giữ nguyên đơn
 * hàng, đánh giá và chứng từ gắn với người đó — thứ công ty có nghĩa vụ giữ.
 *
 * Số đơn hiện ngay trên hàng vì đó là con số quyết định người bấm có dám khóa hay không: khóa một
 * tài khoản có bốn đơn đang chờ khởi hành là chuyện khác hẳn khóa một tài khoản chưa đặt gì.
 */

const ROLE_LABEL: Record<string, string> = {
  admin: "Điều hành",
  guide: "Hướng dẫn viên",
  customer: "Khách hàng",
};

const ROLE_BADGE: Record<string, string> = {
  admin: "bg-violet-50 text-violet-700 border-violet-200",
  guide: "bg-sky-50 text-sky-700 border-sky-200",
  customer: "bg-gray-100 text-gray-600 border-gray-200",
};

const STATUS_BADGE: Record<AdminUserStatus, { label: string; className: string }> = {
  active: { label: "Đang hoạt động", className: "bg-emerald-50 text-emerald-700 border-emerald-200" },
  inactive: { label: "Ngừng hoạt động", className: "bg-gray-100 text-gray-600 border-gray-200" },
  blocked: { label: "Đã khóa", className: "bg-rose-50 text-rose-700 border-rose-200" },
};

const layLoi = (err: unknown, macDinh: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message || macDinh;

export default function UserManagement() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [counts, setCounts] = useState({ admin: 0, guide: 0, customer: 0, blocked: 0 });
  const [loading, setLoading] = useState(true);

  const [keyword, setKeyword] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");

  const [confirming, setConfirming] = useState<AdminUser | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [toast, setToast] = useState("");
  const [error, setError] = useState("");

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getUsers({
        q: keyword.trim() || undefined,
        role: roleFilter || undefined,
        status: statusFilter || undefined,
      });
      setUsers(result?.data ?? []);
      if (result?.counts) setCounts(result.counts);
    } catch (err) {
      console.error("Lỗi tải danh sách tài khoản:", err);
    } finally {
      setLoading(false);
    }
  }, [keyword, roleFilter, statusFilter]);

  useEffect(() => {
    // Chờ một nhịp sau khi gõ, để mỗi phím không thành một lượt gọi máy chủ.
    const timer = setTimeout(taiDanhSach, 300);
    return () => clearTimeout(timer);
  }, [taiDanhSach]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(""), 6000);
    return () => clearTimeout(timer);
  }, [toast]);

  const doiTrangThai = async () => {
    if (!confirming) return;

    setActionLoading(true);
    setError("");

    try {
      const ketQua = await adminService.toggleUserStatus(confirming.id);
      setToast(ketQua.message);
      setConfirming(null);
      await taiDanhSach();
    } catch (err) {
      setError(layLoi(err, "Không đổi được trạng thái tài khoản."));
    } finally {
      setActionLoading(false);
    }
  };

  const dangKhoa = confirming?.status === "active";

  return (
    <UIFlex vertical gap="large" ><div>
        <AntTypography.Title level={3} >Tài khoản</AntTypography.Title>
        <p className="mt-1 text-sm text-gray-500">
          Khóa tài khoản sẽ chặn đăng nhập và thu hồi mọi phiên đang mở. Đơn hàng và đánh giá của
          họ vẫn giữ nguyên.
        </p>
      </div><div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          { label: "Điều hành", value: counts.admin },
          { label: "Hướng dẫn viên", value: counts.guide },
          { label: "Khách hàng", value: counts.customer },
          { label: "Đang bị khóa", value: counts.blocked },
        ].map((o) => (
          <UICard key={o.label} ><UIFlex vertical gap="middle"><p className="text-xs font-medium text-gray-500">{o.label}</p><p className="mt-1 text-2xl font-bold text-gray-900">{o.value}</p></UIFlex></UICard>
        ))}
      </div>{toast && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      )}<div className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div  className="relative min-w-[240px] flex-1"><AntInput prefix={<Search size={16} />} value={keyword} onChange={(e) => setKeyword(e.target.value)} placeholder="Tìm theo tên, email hoặc số điện thoại" style={{ width: "100%" }} /></div>

        <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((roleFilter) ?? "")} onChange={(e) => setRoleFilter(e)} style={{ width: "100%" }} options={[{ value: String(""), label: "Mọi vai trò", disabled: false },{ value: String("admin"), label: "Điều hành", disabled: false },{ value: String("guide"), label: "Hướng dẫn viên", disabled: false },{ value: String("customer"), label: "Khách hàng", disabled: false }].flat().filter((option) => !!option)} />

        <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((statusFilter) ?? "")} onChange={(e) => setStatusFilter(e)} style={{ width: "100%" }} options={[{ value: String(""), label: "Mọi trạng thái", disabled: false },{ value: String("active"), label: "Đang hoạt động", disabled: false },{ value: String("blocked"), label: "Đã khóa", disabled: false },{ value: String("inactive"), label: "Ngừng hoạt động", disabled: false }].flat().filter((option) => !!option)} />
      </div><div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
            <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
          </div>
        ) : users.length === 0 ? (
          <div className="py-20 text-center text-sm text-gray-500">
            Không có tài khoản nào khớp điều kiện lọc.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={users.map((user) => (
                  {key: user.id, cells: [<>
                      <p className="font-semibold text-gray-900">{user.name}</p>
                      <p className="text-xs text-gray-500">{user.email}</p>
                      {user.phone && <p className="text-xs text-gray-400">{user.phone}</p>}
                    </>,<>
                      <span
                        className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${ROLE_BADGE[user.role]}`}
                      >
                        {ROLE_LABEL[user.role] ?? user.role}
                      </span>
                    </>,<>
                      <span
                        className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${STATUS_BADGE[user.status].className}`}
                      >
                        {STATUS_BADGE[user.status].label}
                      </span>
                    </>,<>
                      {user.bookings_count || "—"}
                    </>,<>
                      {user.created_at ? formatDateTime(user.created_at) : "—"}
                    </>,<>
                      <AntButton type={"primary"} onClick={() => {
                          setConfirming(user);
                          setError("");
                        }} htmlType="button">{user.status === "active" ? (
                          <>
                            <Lock className="h-3.5 w-3.5" /> Khóa
                          </>
                        ) : (
                          <>
                            <Unlock className="h-3.5 w-3.5" /> Mở lại
                          </>
                        )}</AntButton>
                    </>], rowProps: {}}
                ))}
    columns={[{ key: "0", title: <>Tài khoản</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Vai trò</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Trạng thái</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Số đơn</>, align: "right", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Tạo lúc</>, align: "left", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Thao tác</>, align: "right", render: (_value, record) => record.cells[5] }]}
    onRow={(record) => record.rowProps}
     />
          </div>
        )}
      </div><Modal
        isOpen={confirming !== null}
        onClose={() => setConfirming(null)}
        title={dangKhoa ? "Khóa tài khoản" : "Mở lại tài khoản"}
      >
        <UIFlex vertical gap={16} ><p className="text-sm text-gray-700">
            {dangKhoa ? (
              <>
                Khóa <strong>{confirming?.email}</strong>? Họ sẽ không đăng nhập được nữa và mọi
                phiên đang mở bị thu hồi ngay.
              </>
            ) : (
              <>
                Mở lại <strong>{confirming?.email}</strong>? Họ sẽ đăng nhập lại được như bình
                thường.
              </>
            )}
          </p>{dangKhoa && (confirming?.bookings_count ?? 0) > 0 && (
            <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
              Tài khoản này có <strong>{confirming?.bookings_count} đơn</strong>. Các đơn ấy vẫn
              giữ nguyên và chuyến vẫn chạy — khóa tài khoản không hủy đơn. Nếu cần hủy, làm ở màn
              Đơn đặt tour.
            </p>
          )}{error && (
            <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
              {error}
            </p>
          )}<UIFlex     justify="end" gap={8}><AntButton onClick={() => setConfirming(null)} htmlType="button">Hủy
            </AntButton><AntButton type={"primary"} onClick={doiTrangThai} disabled={actionLoading} htmlType="button">{actionLoading ? "Đang lưu..." : dangKhoa ? "Khóa tài khoản" : "Mở lại"}</AntButton></UIFlex></UIFlex>
      </Modal></UIFlex>
  );
}
