import { DatePicker, Flex, Typography } from "antd";
import dayjs from "dayjs";
import type { ComponentProps } from "react";
import type { DateTimePicker as OriginalPicker } from "@/components/DateTimePicker";

export function DateTimePicker({ value, onChange, withTime = false, minDate, maxDate, mode, disabled, required, label, placeholder = "Chọn thời gian" }: ComponentProps<typeof OriginalPicker>) {
  const maximum = maxDate ?? (mode === "birthday" ? new Date() : undefined);
  return <Flex vertical gap={4}>
    {label && <Typography.Text>{label}{required ? " *" : ""}</Typography.Text>}
    <DatePicker value={value && dayjs(value).isValid() ? dayjs(value) : null}
      showTime={withTime ? { format: "HH:mm" } : false} format={withTime ? "DD/MM/YYYY HH:mm" : "DD/MM/YYYY"}
      minDate={minDate ? dayjs(minDate) : undefined} maxDate={maximum ? dayjs(maximum) : undefined}
      disabled={disabled} placeholder={placeholder} inputReadOnly={false} allowClear={!required}
      onChange={(date) => onChange(date ? date.format(withTime ? "YYYY-MM-DDTHH:mm" : "YYYY-MM-DD") : "")}
      style={{ width: "100%" }} aria-required={required} />
  </Flex>;
}
