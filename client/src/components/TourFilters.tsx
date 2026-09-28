import { Button, Card, Checkbox, DatePicker, Flex, Form, Grid, Radio, Slider, Typography } from "antd";
import React from "react";
import type { Category, Service } from "@/types";
import dayjs from "dayjs";

const DURATION_OPTIONS = [
  { value: "all", label: "Tất cả" },
  { value: "1", label: "Trong ngày (1 ngày)" },
  { value: "2-3", label: "Ngắn ngày (2 - 3 ngày)" },
  { value: "4+", label: "Dài ngày (Từ 4 ngày trở lên)" },
];

interface TourFiltersProps {
  categories: Category[];
  services: Service[];
  selectedCategories: string[];
  toggleCategory: (slug: string) => void;
  selectedServices: string[];
  toggleService: (id: string) => void;
  selectedDuration: string;
  setSelectedDuration: (val: string) => void;
  priceRange: [number, number];
  setPriceRange: (range: [number, number]) => void;
  maxPrice: number;
  /** Khoảng ngày khách rảnh, dạng YYYY-MM-DD. Chuỗi rỗng nghĩa là không giới hạn đầu đó. */
  departureRange: [string, string];
  setDepartureRange: (range: [string, string]) => void;
  onReset: () => void;
}

export const TourFilters: React.FC<TourFiltersProps> = ({
  categories, services, selectedCategories, toggleCategory, selectedServices, toggleService,
  selectedDuration, setSelectedDuration, priceRange, setPriceRange, maxPrice,
  departureRange, setDepartureRange, onReset,
}) => {
  const screens = Grid.useBreakpoint();
  const today = dayjs().startOf("day");
  return <Card title="Bộ lọc tour" extra={<Button type="link" onClick={onReset}>Xóa lọc</Button>}>
    <Form layout="vertical">
      <Form.Item label="Ngày khởi hành" extra="Chỉ hiện tour còn chỗ và chưa qua hạn chốt trong khoảng ngày này.">
        {screens.md ? <DatePicker.RangePicker style={{ width: "100%" }} format="DD/MM/YYYY" minDate={today}
          allowEmpty={[true, true]} placeholder={["Từ ngày", "Đến ngày"]}
          value={[departureRange[0] ? dayjs(departureRange[0]) : null, departureRange[1] ? dayjs(departureRange[1]) : null]}
          onChange={dates => setDepartureRange([dates?.[0]?.format("YYYY-MM-DD") ?? "", dates?.[1]?.format("YYYY-MM-DD") ?? ""])}
          presets={[
            { label: "7 ngày tới", value: [today, today.add(6, "day")] },
            { label: "30 ngày tới", value: [today, today.add(29, "day")] },
          ]} /> : <Flex vertical gap="small">
          <DatePicker aria-label="Khởi hành từ ngày" style={{ width: "100%" }} format="DD/MM/YYYY" placeholder="Từ ngày" minDate={today}
            maxDate={departureRange[1] ? dayjs(departureRange[1]) : undefined}
            value={departureRange[0] ? dayjs(departureRange[0]) : null}
            onChange={date => setDepartureRange([date?.format("YYYY-MM-DD") ?? "", departureRange[1]])} />
          <DatePicker aria-label="Khởi hành đến ngày" style={{ width: "100%" }} format="DD/MM/YYYY" placeholder="Đến ngày"
            minDate={departureRange[0] ? dayjs(departureRange[0]) : today}
            value={departureRange[1] ? dayjs(departureRange[1]) : null}
            onChange={date => setDepartureRange([departureRange[0], date?.format("YYYY-MM-DD") ?? ""])} />
        </Flex>}
      </Form.Item>
      <Form.Item label="Danh mục tour">
        <Flex vertical gap="small">
          {categories.length ? categories.map(cat => <Checkbox key={cat.id} checked={selectedCategories.includes(cat.slug)} onChange={() => toggleCategory(cat.slug)}>{cat.name}</Checkbox>)
            : <Typography.Text type="secondary">Chưa có danh mục.</Typography.Text>}
        </Flex>
      </Form.Item>
      <Form.Item label="Mức giá tối đa">
        <Slider min={0} max={maxPrice} step={500000} value={priceRange[1]} onChange={value => setPriceRange([0, value])}
          tooltip={{ formatter: value => new Intl.NumberFormat("vi-VN").format(value ?? 0) + "đ" }} />
        <Typography.Text>Tới {new Intl.NumberFormat("vi-VN").format(priceRange[1])}đ</Typography.Text>
      </Form.Item>
      <Form.Item label="Thời gian">
        <Radio.Group value={selectedDuration} onChange={event => setSelectedDuration(event.target.value)}>
          <Flex vertical gap="small">{DURATION_OPTIONS.map(option => <Radio key={option.value} value={option.value}>{option.label}</Radio>)}</Flex>
        </Radio.Group>
      </Form.Item>
      <Form.Item label="Dịch vụ đi kèm" style={{ marginBottom: 0 }}>
        <Flex vertical gap="small">
          {services.length ? services.map(service => <Checkbox key={service.id} checked={selectedServices.includes(String(service.id))} onChange={() => toggleService(String(service.id))}>{service.name}</Checkbox>)
            : <Typography.Text type="secondary">Chưa có dịch vụ.</Typography.Text>}
        </Flex>
      </Form.Item>
    </Form>
  </Card>;
};
