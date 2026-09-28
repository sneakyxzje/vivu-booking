import { App, ConfigProvider } from "antd";
import type { PropsWithChildren } from "react";
import { StyleProvider } from "@ant-design/cssinjs";
import viVN from "antd/locale/vi_VN";
import dayjs from "dayjs";
import "dayjs/locale/vi";

dayjs.locale("vi");

export function CustomerUIProvider({ children }: PropsWithChildren) {
  return (
    <StyleProvider layer>
      <ConfigProvider locale={viVN} theme={{
        token: {
          colorPrimary: "#0b817a",
          borderRadius: 10,
          fontFamily: "Inter, system-ui, sans-serif",
          fontSize: 14,
          controlHeight: 44,
          controlHeightLG: 48,
        },
        components: { Button: { fontWeight: 600 }, Form: { verticalLabelPadding: "0 0 6px" } },
      }}>
        <App style={{ minHeight: "100vh" }}>{children}</App>
      </ConfigProvider>
    </StyleProvider>
  );
}
