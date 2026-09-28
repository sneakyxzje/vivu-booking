import { App, Form, Input } from "antd";

/** Ant Design owns focus, validation and cancellation for the short operational notes. */
export function useAdminPrompt() {
  const { modal } = App.useApp();
  const [form] = Form.useForm<{ value: string }>();
  return async (title: string, initialValue = "", minLength = 0): Promise<string | null> => {
    form.setFieldsValue({ value: initialValue });
    const confirmed = await modal.confirm({
      title, icon: null, okText: "Xác nhận", cancelText: "Đóng", mask: { closable: false },
      content: <Form form={form} layout="vertical">
        <Form.Item name="value" label="Nội dung" rules={minLength ? [{
          validator: async (_, value: string) => {
            if ((value ?? "").trim().length < minLength) throw new Error(`Nhập ít nhất ${minLength} ký tự.`);
          },
        }] : []}>
          <Input.TextArea autoFocus rows={3} maxLength={1000} showCount />
        </Form.Item>
      </Form>,
      onOk: () => form.validateFields(),
    });
    return confirmed ? form.getFieldValue("value") ?? "" : null;
  };
}
