import { Suspense, useState } from "react";
import { Link, Outlet, useLocation, useNavigate } from "react-router-dom";
import { App, Avatar, Badge, Button, ConfigProvider, Drawer, Dropdown, Flex, Grid, Layout, Menu, Skeleton, Typography } from "antd";
import { StyleProvider } from "@ant-design/cssinjs";
import viVN from "antd/locale/vi_VN";
import dayjs from "dayjs";
import "dayjs/locale/vi";
import { Bell, CalendarCheck, ClipboardList, House, Menu as MenuIcon, Map, ArrowRightLeft, TriangleAlert, X } from "lucide-react";
import { useAuth } from "@/hooks/useAuth";
import { useNotifications } from "@/hooks/useNotifications";
import { sidebarColors, sidebarMenuTokens } from "@/components/navigation/sidebarTheme";

dayjs.locale("vi");

const navItems = [
  { key: "/guide/dashboard", label: "Tổng quan", icon: <House size={18} /> },
  { key: "/guide/notifications", label: "Thông báo", icon: <Bell size={18} /> },
  { key: "/guide/assignments", label: "Chuyến được giao", icon: <CalendarCheck size={18} /> },
  { key: "/guide/tours", label: "Tour của tôi", icon: <Map size={18} /> },
  { key: "/guide/bookings", label: "Đặt chỗ", icon: <ClipboardList size={18} /> },
  { key: "/guide/handovers", label: "Bàn giao đoàn", icon: <ArrowRightLeft size={18} /> },
  { key: "/guide/incidents", label: "Báo sự cố", icon: <TriangleAlert size={18} /> },
];

function GuideShell() {
  const { user, logout } = useAuth();
  const { unread } = useNotifications();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const screens = Grid.useBreakpoint();
  const [menuOpen, setMenuOpen] = useState(false);
  const selectedKey = pathname.startsWith("/guide/attendance/") ? "/guide/tours"
    : navItems.find(item => pathname.startsWith(item.key))?.key ?? "/guide/dashboard";
  const title = pathname.startsWith("/guide/attendance/") ? "Điểm danh đoàn" : navItems.find(item => item.key === selectedKey)?.label;

  const handleLogout = () => { logout(); navigate("/login"); };
  const sidebar = <Flex vertical style={{ height: "100%", minHeight: 0, background: sidebarColors.background }}>
    <Flex vertical gap="middle" style={{ padding: 24, borderBottom: `1px solid ${sidebarColors.border}` }}>
      <Link to="/"><Typography.Title level={4} style={{ margin: 0, color: sidebarColors.text }}>VivuBooking</Typography.Title></Link>
      <Flex gap="small" align="center">
        <Avatar style={{ background: "#0b817a", flexShrink: 0 }}>{user?.name?.charAt(0).toUpperCase() ?? "H"}</Avatar>
        <Flex vertical style={{ minWidth: 0 }}>
          <Typography.Text strong ellipsis style={{ color: sidebarColors.text }}>{user?.name ?? "Hướng dẫn viên"}</Typography.Text>
          <Typography.Text ellipsis style={{ color: sidebarColors.secondaryText }}>{user?.email}</Typography.Text>
        </Flex>
      </Flex>
    </Flex>
    <Menu theme="dark" mode="inline" selectedKeys={[selectedKey]} style={{ flex: 1, padding: "12px 8px", borderInlineEnd: 0 }}
      items={navItems.map(item => ({
        ...item, label: <Flex justify="space-between" align="center" gap="small">
          <span>{item.label}</span>{item.key === "/guide/notifications" && <Badge count={unread} overflowCount={99} />}
        </Flex>
      }))}
      onClick={({ key }) => { navigate(key); setMenuOpen(false); }} />
    <Flex vertical gap="small" style={{ padding: 16, borderTop: `1px solid ${sidebarColors.border}` }}>
      <Button ghost onClick={() => navigate("/")}>Về trang chủ</Button>
      <Button danger ghost onClick={handleLogout}>Đăng xuất</Button>
    </Flex>
  </Flex>;

  return <Layout style={{ minHeight: "100vh" }}>
    {screens.lg && <Layout.Sider width={256} theme="dark" style={{ position: "sticky", top: 0, height: "100vh", overflowY: "auto", borderInlineEnd: `1px solid ${sidebarColors.border}` }}>{sidebar}</Layout.Sider>}
    <Drawer title={<Typography.Text strong style={{ color: sidebarColors.text }}>Hướng dẫn viên</Typography.Text>} placement="left" open={!screens.lg && menuOpen} onClose={() => setMenuOpen(false)}
      closeIcon={<X size={18} color={sidebarColors.secondaryText} />}
      size={288} styles={{ section: { background: sidebarColors.background }, header: { borderBottom: `1px solid ${sidebarColors.border}` }, body: { padding: 0, background: sidebarColors.background } }}>{sidebar}</Drawer>
    <Layout style={{ minWidth: 0 }}>
      <Layout.Header style={{ background: "#fff", padding: screens.md ? "0 24px" : "0 16px", height: 72, lineHeight: "normal", position: "sticky", top: 0, zIndex: 20, borderBottom: "1px solid #f0f0f0" }}>
        <Flex align="center" justify="space-between" gap="middle" style={{ height: "100%" }}>
          <Flex align="center" gap="small" style={{ minWidth: 0 }}>
            {!screens.lg && <Button type="text" icon={<MenuIcon size={20} />} aria-label="Mở điều hướng" onClick={() => setMenuOpen(true)} />}
            <Flex vertical style={{ minWidth: 0 }}>
              <Typography.Text type="secondary">Hướng dẫn viên</Typography.Text>
              <Typography.Text strong ellipsis>{title}</Typography.Text>
            </Flex>
          </Flex>
          <Dropdown trigger={["click"]} menu={{
            items: [{ key: "home", label: "Về trang chủ" }, { key: "logout", label: "Đăng xuất", danger: true }],
            onClick: ({ key }) => key === "logout" ? handleLogout() : navigate("/")
          }}>
            <Button type="text" aria-label="Tài khoản hướng dẫn viên"><Avatar size="small" style={{ background: "#0b817a" }}>{user?.name?.charAt(0).toUpperCase() ?? "H"}</Avatar>{screens.md ? user?.name : null}</Button>
          </Dropdown>
        </Flex>
      </Layout.Header>
      <Layout.Content style={{ padding: screens.md ? 24 : 16, minWidth: 0 }}>
        <Suspense fallback={<Skeleton active paragraph={{ rows: 8 }} />}><Outlet /></Suspense>
      </Layout.Content>
    </Layout>
  </Layout>;
}

export function GuideLayout() {
  return <StyleProvider layer><ConfigProvider locale={viVN} theme={{
    token: { colorPrimary: "#0b817a", fontFamily: "Inter, system-ui, sans-serif", borderRadius: 8, controlHeight: 44 },
    components: { Menu: { ...sidebarMenuTokens, itemHeight: 44 }, Layout: { siderBg: sidebarColors.background }, Button: { fontWeight: 600 } },
  }}><App><GuideShell /></App></ConfigProvider></StyleProvider>;
}

export default GuideLayout;
