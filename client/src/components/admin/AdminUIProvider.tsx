import type { PropsWithChildren } from "react";
import { StyleProvider } from "@ant-design/cssinjs";
import { App, ConfigProvider } from "antd";
import viVN from "antd/locale/vi_VN";
import dayjs from "dayjs";
import "dayjs/locale/vi";
import { sidebarColors, sidebarMenuTokens } from "@/components/navigation/sidebarTheme";

dayjs.locale("vi");

/** Shared defaults for admin screens. Prefer component props and tokens over CSS overrides. */
export function AdminUIProvider({ children }: PropsWithChildren) {
  return (
    <StyleProvider layer>
      <ConfigProvider locale={viVN} theme={{
        token: {
          colorPrimary: "#0b817a",
          borderRadius: 8,
          fontFamily: "Inter, system-ui, sans-serif",
          fontSize: 14,
          controlHeight: 40,
        },
        components: {
          Menu: {
            ...sidebarMenuTokens,
            itemHeight: 42,
          },
          Layout: {
            siderBg: sidebarColors.background,
          },
        },
      }}>
        <App>{children}</App>
      </ConfigProvider>
    </StyleProvider>
  );
}
