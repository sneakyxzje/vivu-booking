import type { PropsWithChildren } from "react";
import { StyleProvider } from "@ant-design/cssinjs";
import { App, ConfigProvider } from "antd";
import viVN from "antd/locale/vi_VN";
import dayjs from "dayjs";
import "dayjs/locale/vi";

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
            darkItemBg: "#0f172a",
            darkSubMenuItemBg: "#0f172a",
            darkPopupBg: "#0f172a",
            darkItemColor: "#94a3b8",
            darkItemHoverColor: "#ffffff",
            darkItemHoverBg: "#1e293b",
            darkItemSelectedColor: "#ffffff",
            darkItemSelectedBg: "rgba(11, 129, 122, 0.22)",
            darkGroupTitleColor: "#64748b",
            itemBorderRadius: 8,
            itemHeight: 42,
          },
          Layout: {
            siderBg: "#0f172a",
          },
        },
      }}>
        <App>{children}</App>
      </ConfigProvider>
    </StyleProvider>
  );
}
