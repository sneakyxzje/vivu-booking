import { DatePicker, Flex, Typography } from "antd";
import dayjs from "dayjs";
import type { ComponentProps } from "react";
import type { DateRangePicker as OriginalPicker } from "@/components/DateRangePicker";
export type { DateRange } from "@/components/DateRangePicker";

export function DateRangePicker({ value, onChange, withTime = false, minDate, maxDate, presets = "past", label }: ComponentProps<typeof OriginalPicker>) {
  const now = dayjs();
  const ranges = presets === "past" ? [
    { label: "Hôm nay", value: [now.startOf("day"), now.endOf("day")] as [dayjs.Dayjs, dayjs.Dayjs] },
    { label: "7 ngày qua", value: [now.subtract(6, "day").startOf("day"), now.endOf("day")] as [dayjs.Dayjs, dayjs.Dayjs] },
    { label: "Tháng này", value: [now.startOf("month"), now] as [dayjs.Dayjs, dayjs.Dayjs] },
  ] : [
    { label: "7 ngày tới", value: [now.startOf("day"), now.add(6, "day").endOf("day")] as [dayjs.Dayjs, dayjs.Dayjs] },
    { label: "30 ngày tới", value: [now.startOf("day"), now.add(29, "day").endOf("day")] as [dayjs.Dayjs, dayjs.Dayjs] },
  ];
  return <Flex vertical gap={4}>
    {label && <Typography.Text>{label}</Typography.Text>}
    <DatePicker.RangePicker value={[value.from ? dayjs(value.from) : null, value.to ? dayjs(value.to) : null]}
      allowEmpty={[true, true]} showTime={withTime ? { format: "HH:mm" } : false}
      format={withTime ? "DD/MM/YYYY HH:mm" : "DD/MM/YYYY"} presets={ranges}
      minDate={minDate ? dayjs(minDate) : undefined} maxDate={maxDate ? dayjs(maxDate) : undefined}
      placeholder={["Từ ngày", "Đến ngày"]} style={{ width: "100%" }}
      onChange={(dates) => onChange({
        from: dates?.[0]?.format(withTime ? "YYYY-MM-DDTHH:mm" : "YYYY-MM-DD") ?? "",
        to: dates?.[1]?.format(withTime ? "YYYY-MM-DDTHH:mm" : "YYYY-MM-DD") ?? "",
      })} />
  </Flex>;
}
