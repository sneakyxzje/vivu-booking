import React, { useState } from "react";
import { Avatar, Button, Divider, Drawer, Dropdown, Flex, Grid, Layout, Menu, Typography, theme } from "antd";
import { ChevronDown, ExternalLink, LogOut, Menu as MenuIcon, UserRound, X } from "lucide-react";
import { Link, Outlet, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { AdminNotificationBell } from "./AdminNotificationBell";
import { AdminUIProvider } from "./AdminUIProvider";

type NavLeaf = { to: string; label: string };

type NavEntry =
  | { kind: "link"; to: string; label: string; icon: React.ReactNode }
  | {
      kind: "group";
      id: string;
      label: string;
      icon: React.ReactNode;
      items: NavLeaf[];
    };

const icon = (d: string, strokeWidth = 2) => (
  <svg
    width={20} height={20}
    fill="none"
    stroke="currentColor"
    viewBox="0 0 24 24"
  >
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={strokeWidth}
      d={d}
    />
  </svg>
);

const navEntries: NavEntry[] = [
  {
    kind: "link",
    to: "/admin/dashboard",
    label: "Tổng quan",
    icon: icon(
      "M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z",
    ),
  },
  {
    kind: "group",
    id: "san-pham",
    label: "Sản phẩm",
    icon: icon(
      "M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7",
    ),
    items: [
      { to: "/admin/tours", label: "Quản lý tour" },
      { to: "/admin/categories", label: "Danh mục tour" },
      /*
       * "Dịch vụ đi kèm", không phải "Dịch vụ phát sinh".
       *
       * Màn này quản lý những thứ tour ĐÃ bao gồm trong giá bán — xe đưa đón, bảo hiểm, vé tham
       * quan. Chi phí phát sinh thật, tức khoản sinh ra ngoài ý muốn khi có bão hay xe hỏng, nằm
       * ở "Sự cố dọc đường" và đi qua bảng `booking_surcharges`.
       *
       * Hai chuyện ngược nhau mà tên cũ dùng chung một chữ.
       */
      { to: "/admin/services", label: "Dịch vụ đi kèm" },
      { to: "/admin/discount-codes", label: "Mã giảm giá" },
      /*
       * Đánh giá nằm ở nhóm "sản phẩm" chứ không ở nhóm "đơn hàng": nó là thứ hiện trên trang
       * bán tour và kéo điểm tour lên xuống, không phải một bước trong vòng đời một đơn.
       */
      { to: "/admin/reviews", label: "Đánh giá của khách" },
    ],
  },
  {
    kind: "group",
    id: "don-hang",
    label: "Đơn hàng",
    icon: icon(
      "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
    ),
    items: [
      // "Đơn đặt hàng", không phải "Hoá đơn": màn này quản lý ĐƠN suốt vòng đời của nó — chờ thanh
      // toán, đã cọc, đã chuyển chuyến, đã hủy. Hoá đơn là một chứng từ, và phần lớn đơn ở đây chưa
      // có chứng từ nào.
      { to: "/admin/bookings", label: "Đơn đặt hàng" },
      { to: "/admin/group-bookings", label: "Booking theo đoàn" },
      { to: "/admin/change-requests", label: "Yêu cầu huỷ" },
      /*
       * Hai mục tiền, đặt cạnh nhau và đúng thứ tự vào trước ra sau.
       *
       * "Sổ giao dịch" là toàn bộ dòng tiền; "Hoàn tiền" là một lát cắt của nó — phần công ty còn
       * nợ khách. Trước đây chỉ có mục thứ hai, tức tiền đi ra có màn riêng còn tiền đi vào thì
       * phải mở từng đơn mới xem được, trong khi công ty thu nhiều hơn chi rất nhiều lần.
       */
      // Một mục duy nhất cho tiền: sổ giao dịch, phải thu và phải trả là ba tab bên trong nó.
      // Tách ra ba mục thì người dùng phải nhớ vào đâu để hỏi gì, trong khi cả ba đọc chung một sổ.
      { to: "/admin/transactions", label: "Sổ giao dịch" },
      /*
       * Không còn mục "Chính sách hủy".
       *
       * Bảng phí là hằng số của hệ thống, viết trong `CancellationPolicyService::DEFAULT_RULES` và
       * trình bày cho khách ở trang chính sách. Nó buộc chặt với tỷ lệ cọc và hạn trả nốt — ba con
       * số được chọn cùng nhau sao cho phí tại hạn trả nốt vừa đúng bằng tiền cọc. Một ô nhập cho
       * phép phá mối buộc ấy mà không cảnh báo gì, và hậu quả là tiền thật.
       */
    ],
  },
  {
    kind: "group",
    id: "dieu-hanh",
    label: "Điều hành",
    icon: icon(
      "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z",
    ),
    items: [
      { to: "/admin/schedules", label: "Quản lý chuyến" },
      { to: "/admin/guides", label: "Hướng dẫn viên" },
      { to: "/admin/handovers", label: "Bàn giao HDV" },
      { to: "/admin/incidents", label: "Chi phí phát sinh" },
      { to: "/admin/attendance-reports", label: "Báo cáo điểm danh" },
    ],
  },
  {
    kind: "link",
    to: "/admin/notifications",
    label: "Thông báo",
    icon: icon(
      "M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9",
    ),
  },
  {
    /*
     * Tài khoản đứng riêng chứ không nằm trong nhóm "điều hành chuyến": nó cắt ngang cả ba nhóm
     * kia — cùng một màn quản lý cả khách hàng, hướng dẫn viên lẫn người điều hành.
     */
    kind: "link",
    to: "/admin/users",
    label: "Tài khoản",
    icon: icon(
      "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
    ),
  },
  {
    kind: "link",
    to: "/admin/contact-messages",
    label: "Liên hệ & bản tin",
    icon: icon(
      "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
    ),
  },
  {
    kind: "link",
    to: "/admin/audit-logs",
    label: "Nhật ký hệ thống",
    icon: icon("M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"),
  },
];


function AdminShell() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const screens = Grid.useBreakpoint();
  const { token } = theme.useToken();
  const [menuOpen, setMenuOpen] = useState(false);
  const sectionPath = pathname.startsWith("/admin/tour-schedules/") ? "/admin/schedules" : pathname;
  const leaves = navEntries.flatMap((entry) => entry.kind === "link" ? [entry] : entry.items);
  const active = leaves
    .filter((entry) => sectionPath === entry.to || sectionPath.startsWith(entry.to + "/"))
    .sort((a, b) => b.to.length - a.to.length)[0];
  const activeGroup = navEntries.find((entry) => entry.kind === "group" && entry.items.some((item) => item.to === active?.to));
  const section = activeGroup?.label ?? "Khu vực quản trị";
  const pageTitle = pathname === "/admin/tours/create" ? "Tạo tour mới"
    : /^\/admin\/tours\/[^/]+\/edit$/.test(pathname) ? "Chỉnh sửa tour"
    : /^\/admin\/tours\/[^/]+$/.test(pathname) ? "Chi tiết tour"
    : pathname.startsWith("/admin/tour-schedules/") ? "Điểm danh chuyến"
    : pathname === "/admin/sandbox" ? "Sân thử nghiệp vụ"
    : pathname === "/admin/transactions" ? "Tài chính"
    : active?.label ?? "Quản trị";
  const userName = user?.name?.trim() || "Điều hành";
  const initials = userName.split(/\s+/).slice(-2).map((part) => part[0]).join("").toUpperCase();
  const items = navEntries.map((entry) => entry.kind === "link"
    ? { key: entry.to, label: entry.label, icon: entry.icon }
    : { key: entry.id, label: entry.label, icon: entry.icon, children: entry.items.map((item) => ({ key: item.to, label: item.label })) });
  const menu = <Menu theme="dark" mode="inline" items={items} selectedKeys={active ? [active.to] : []}
    defaultOpenKeys={activeGroup?.kind === "group" ? [activeGroup.id] : []}
    style={{ padding: "12px 8px", borderInlineEnd: 0 }}
    onClick={({ key }) => { navigate(key); setMenuOpen(false); }} />;
  const brand = <Link to="/admin/dashboard" onClick={() => setMenuOpen(false)} aria-label="VivuBooking — tổng quan quản trị">
    <Flex align="center" gap={12}>
      <Avatar shape="square" size={36} style={{ background: token.colorPrimary, color: "#ffffff", fontWeight: 700 }}>VB</Avatar>
      <Flex vertical gap={2}>
        <Typography.Text strong style={{ color: "#f8fafc", fontSize: 17 }}>VivuBooking</Typography.Text>
        <Typography.Text style={{ color: "#94a3b8", fontSize: 12 }}>Trang quản trị</Typography.Text>
      </Flex>
    </Flex>
  </Link>;

  return <Layout style={{ minHeight: "100vh" }}>
    {screens.lg && <Layout.Sider width={264} theme="dark"
      style={{ position: "sticky", top: 0, height: "100vh", overflowY: "auto", borderInlineEnd: "1px solid #1e293b" }}>
      <Flex align="center" style={{ height: 80, paddingInline: 24, borderBottom: "1px solid #1e293b" }}>{brand}</Flex>
      {menu}
    </Layout.Sider>}
    <Drawer title={brand} placement="left" size={280} open={!screens.lg && menuOpen} onClose={() => setMenuOpen(false)}
      closeIcon={<X size={18} color="#94a3b8" />}
      styles={{
        section: { background: "#0f172a" },
        header: { borderBottom: "1px solid #1e293b" },
        body: { padding: 0, background: "#0f172a" },
      }}>
      {menu}
    </Drawer>
    <Layout style={{ minWidth: 0 }}>
      <Layout.Header style={{
        background: token.colorBgContainer,
        paddingInline: screens.md ? 28 : 16,
        height: 80,
        lineHeight: "normal",
        position: "sticky",
        top: 0,
        zIndex: 20,
        borderBottom: `1px solid ${token.colorBorderSecondary}`,
      }}>
        <Flex justify="space-between" align="center" gap={screens.md ? 24 : 12} style={{ height: "100%" }}>
          <Flex align="center" gap={14} style={{ minWidth: 0, flex: 1 }}>
            {!screens.lg && <Button aria-label="Mở menu quản trị" icon={<MenuIcon size={18} />} onClick={() => setMenuOpen(true)} />}
            <Flex vertical gap={4} style={{ minWidth: 0 }}>
              {screens.sm && <Typography.Text type="secondary" style={{ fontSize: 12 }}>{section}</Typography.Text>}
              <Typography.Text strong ellipsis style={{ fontSize: screens.md ? 18 : 15 }} title={pageTitle}>{pageTitle}</Typography.Text>
            </Flex>
          </Flex>
          <Flex align="center" gap={screens.md ? 12 : 8} style={{ flexShrink: 0 }}>
            {screens.md && <Button type="text" href="/" target="_blank" rel="noopener noreferrer" icon={<ExternalLink size={16} />}>Xem website</Button>}
            <AdminNotificationBell />
            {screens.md && <Divider orientation="vertical" style={{ height: 28, marginInline: 4 }} />}
            <Dropdown trigger={["click"]} placement="bottomRight" menu={{ items: [
              { key: "identity", disabled: true, label: userName, icon: <UserRound size={16} /> },
              { type: "divider" },
              { key: "website", label: <a href="/" target="_blank" rel="noopener noreferrer">Xem website</a>, icon: <ExternalLink size={16} /> },
              { key: "logout", label: "Đăng xuất", icon: <LogOut size={16} />, danger: true, onClick: () => { logout(); navigate("/login"); } },
            ] }}>
              <Button type="text" aria-label={`Tài khoản ${userName}`} style={{ height: 52, paddingInline: screens.md ? 8 : 4 }}>
                <Flex align="center" gap={10}>
                  <Avatar size={36} style={{ background: token.colorPrimaryBg, color: token.colorPrimary, fontWeight: 600 }}>{initials}</Avatar>
                  {screens.md && <Flex vertical align="start" gap={3} style={{ maxWidth: 160 }}>
                    <Typography.Text strong ellipsis style={{ maxWidth: "100%" }}>{userName}</Typography.Text>
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>Quản trị viên</Typography.Text>
                  </Flex>}
                  {screens.sm && <ChevronDown size={14} color={token.colorTextSecondary} />}
                </Flex>
              </Button>
            </Dropdown>
          </Flex>
        </Flex>
      </Layout.Header>
      <Layout.Content style={{ padding: screens.md ? token.paddingLG : token.padding, minWidth: 0 }}><Outlet /></Layout.Content>
    </Layout>
  </Layout>;
}
export const AdminLayout = () => <AdminUIProvider><AdminShell /></AdminUIProvider>;
