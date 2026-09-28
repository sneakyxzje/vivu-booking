import { Image } from "antd";

export function ItineraryGallery({ images, day }: { images?: string[] | null; day: number }) {
  if (!images?.length) return null;
  return <Image.PreviewGroup>
    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: 12, margin: "16px 0" }}>
      {images.map((url, index) => <Image key={`${url}-${index}`} src={url}
        alt={`Ngày ${day} · Ảnh ${index + 1}`} loading="lazy" width="100%" height={180}
        style={{ objectFit: "cover", borderRadius: 8 }} />)}
    </div>
  </Image.PreviewGroup>;
}
