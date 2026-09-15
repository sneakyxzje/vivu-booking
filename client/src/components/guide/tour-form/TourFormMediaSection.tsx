import { App, Button, Card, Col, Flex, Image, Row, Typography, Upload } from "antd";
import { ImagePlus, Trash2 } from "lucide-react";

interface Props {
  labelClass: string;
  thumbnailName: string | null;
  thumbnailPreview: string;
  thumbnailUrl: string;
  imagePreviews: string[];
  onThumbnailChange: (file: File) => void;
  onThumbnailRemove: () => void;
  onGalleryChange: (files: File[]) => void;
  onRemoveGalleryImage: (index: number) => void;
}
export function TourFormMediaSection({ thumbnailName, thumbnailPreview, thumbnailUrl, imagePreviews, onThumbnailChange, onThumbnailRemove, onGalleryChange, onRemoveGalleryImage }: Props) {
  const { message } = App.useApp();
  const valid = (file: File) => {
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 5 * 1024 * 1024) {
      void message.error("Chọn ảnh JPG, PNG hoặc WEBP có dung lượng tối đa 5MB.");
      return false;
    }
    return true;
  };
  const cover = thumbnailPreview || thumbnailUrl;
  return <Card title="Hình ảnh">
    <Flex vertical gap="large">
      <Typography.Text type="secondary">Ảnh bìa hiển thị ở danh sách tour. PNG, JPG hoặc WEBP, tối đa 5MB mỗi ảnh.</Typography.Text>
      <Flex vertical gap="middle">
        <Typography.Title level={5}>Ảnh bìa</Typography.Title>
        {cover && <Image src={cover} alt="Ảnh bìa tour" width={260} />}
        {cover && <Typography.Text type="secondary">{thumbnailName ?? "Ảnh đang dùng"}</Typography.Text>}
        <Flex gap="small" wrap>
          <Upload accept="image/png,image/jpeg,image/webp" showUploadList={false}
            beforeUpload={(file) => { if (valid(file)) onThumbnailChange(file); return false; }}>
            <Button icon={<ImagePlus size={16} />}>{cover ? "Đổi ảnh bìa" : "Chọn ảnh bìa"}</Button>
          </Upload>
          {cover && <Button danger icon={<Trash2 size={16} />} onClick={onThumbnailRemove}>Bỏ ảnh bìa</Button>}
        </Flex>
      </Flex>
      <Flex vertical gap="middle">
        <Typography.Title level={5}>Bộ ảnh tour ({imagePreviews.length})</Typography.Title>
        <Upload accept="image/png,image/jpeg,image/webp" multiple showUploadList={false}
          beforeUpload={(file) => { if (valid(file)) onGalleryChange([file]); return false; }}>
          <Button icon={<ImagePlus size={16} />}>Thêm ảnh</Button>
        </Upload>
        <Typography.Text type="secondary">Chọn thêm ảnh sẽ nối vào bộ ảnh hiện tại. Ảnh được tải lên khi bạn lưu tour.</Typography.Text>
        <Row gutter={[12, 12]}>
          {imagePreviews.map((preview, index) => <Col key={preview} xs={12} md={8} xl={6}>
            <Card size="small" cover={<Image src={preview} alt={`Ảnh tour ${index + 1}`} width="100%" styles={{ image: { height: 150, objectFit: "cover" } }} />}>
              <Button danger block icon={<Trash2 size={16} />} onClick={() => onRemoveGalleryImage(index)}>Xóa ảnh {index + 1}</Button>
            </Card>
          </Col>)}
        </Row>
      </Flex>
    </Flex>
  </Card>;
}
