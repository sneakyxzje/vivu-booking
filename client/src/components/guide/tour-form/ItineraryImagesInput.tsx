import { useMemo, useState } from "react";
import { App, Image, Upload } from "antd";
import type { UploadFile, UploadProps } from "antd";
import type { ItineraryFormItem } from "./types";

interface Props {
  item: ItineraryFormItem;
  onChange: (changes: Partial<ItineraryFormItem>) => void;
}

export function ItineraryImagesInput({ item, onChange }: Props) {
  const { message } = App.useApp();
  const [preview, setPreview] = useState("");
  const fileList = useMemo<UploadFile[]>(() => [
    ...(item.images ?? []).map((url, index) => ({ uid: `saved-${index}`, name: `Ảnh ${index + 1}`, url, status: "done" as const })),
    ...(item.image_files ?? []).map((file, index) => ({
      uid: (file as File & { uid?: string }).uid ?? `new-${index}`, name: file.name, originFileObj: file as UploadFile["originFileObj"],
    })),
  ], [item.images, item.image_files]);
  const beforeUpload: UploadProps["beforeUpload"] = (file, batch) => {
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
      void message.error("Chọn ảnh JPG, PNG hoặc WebP.");
      return Upload.LIST_IGNORE;
    }
    if (file.size > 5 * 1024 * 1024) {
      void message.error(`Ảnh ${file.name} vượt quá 5 MB.`);
      return Upload.LIST_IGNORE;
    }
    if (fileList.length + batch.length > 8) {
      if (file.uid === batch[0].uid) void message.error("Mỗi ngày tối đa 8 ảnh.");
      return Upload.LIST_IGNORE;
    }
    return false;
  };
  const showPreview = async (file: UploadFile) => {
    if (file.url) return setPreview(file.url);
    if (!file.originFileObj) return;
    const reader = new FileReader();
    reader.onload = () => setPreview(String(reader.result));
    reader.onerror = () => void message.error("Không đọc được ảnh. Vui lòng chọn lại.");
    reader.readAsDataURL(file.originFileObj);
  };

  return <>
    <Upload accept="image/jpeg,image/png,image/webp" multiple listType="picture-card"
      fileList={fileList} beforeUpload={beforeUpload} onPreview={showPreview}
      onChange={({ fileList: next }) => onChange({
        images: next.filter(file => file.url).map(file => file.url!),
        image_files: next.filter(file => file.originFileObj).map(file => file.originFileObj!),
      })}>
      {fileList.length < 8 && <span>+ Thêm ảnh</span>}
    </Upload>
    {preview && <Image style={{ display: "none" }} src={preview} preview={{
      open: true, onOpenChange: open => { if (!open) setPreview(""); },
    }} />}
  </>;
}
