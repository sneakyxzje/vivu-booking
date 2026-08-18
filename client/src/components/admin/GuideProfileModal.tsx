import React, { useEffect, useState } from "react";
import type { Category, Guide, GuideProfilePayload } from "@/types";
import adminService from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";

/**
 * Hồ sơ năng lực hướng dẫn viên — trả lời "ai phù hợp", khác với "ai đang rảnh".
 *
 * Tách khỏi biểu mẫu sửa tài khoản vì hai thứ khác bản chất: một bên là tài khoản đăng nhập, một
 * bên là thông tin nghề nghiệp. Gộp lại thì mỗi lần tạm ngưng một người cũng phải gửi kèm cả danh
 * sách ngôn ngữ.
 *
 * Điều quan trọng nhất màn này phải nói rõ: **không ô nào ở đây chặn được phân công.** Tất cả chỉ
 * để xếp thứ tự và nhắc — điền sai hay bỏ trống đều không khóa ai ra khỏi hệ thống.
 */
interface Props {
  guide: Guide | null;
  onClose: () => void;
  onSaved: (message: string) => void;
  onError: (message: string) => void;
}

/** Ô nhập danh sách: người ta gõ "Hạ Long, Ninh Bình" quen hơn là bấm thêm từng dòng. */
const tachDanhSach = (gia: string): string[] =>
  gia
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);

export const GuideProfileModal: React.FC<Props> = ({ guide, onClose, onSaved, onError }) => {
  const [categories, setCategories] = useState<Category[]>([]);
  const [saving, setSaving] = useState(false);

  const [languages, setLanguages] = useState("");
  const [regions, setRegions] = useState("");
  const [maxGroupSize, setMaxGroupSize] = useState("");
  const [note, setNote] = useState("");
  const [categoryIds, setCategoryIds] = useState<number[]>([]);

  useEffect(() => {
    if (!guide) return;

    const hoSo = guide.guide_profile;

    setLanguages((hoSo?.languages ?? []).join(", "));
    setRegions((hoSo?.regions ?? []).join(", "));
    setMaxGroupSize(hoSo?.max_group_size ? String(hoSo.max_group_size) : "");
    setNote(hoSo?.note ?? "");
    setCategoryIds((guide.guide_categories ?? []).map((item) => item.id));
  }, [guide]);

  useEffect(() => {
    adminService
      .getCategories()
      .then((res) => setCategories(res?.data ?? []))
      .catch((err) => console.error("Lỗi tải danh mục:", err));
  }, []);

  const luu = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!guide) return;

    setSaving(true);

    const payload: GuideProfilePayload = {
      languages: tachDanhSach(languages),
      regions: tachDanhSach(regions),
      max_group_size: maxGroupSize ? Number(maxGroupSize) : null,
      note: note.trim() || null,
      category_ids: categoryIds,
    };

    try {
      onSaved(await adminService.updateGuideProfile(guide.id, payload));
      onClose();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      onError(response?.message || "Không lưu được hồ sơ năng lực.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      isOpen={!!guide}
      onClose={onClose}
      title={`Hồ sơ năng lực: ${guide?.name ?? ""}`}
      subtitle="Dùng để xếp thứ tự khi phân công — toàn bộ là gợi ý, không ô nào chặn được ai."
      onSubmit={luu}
      size="xl"
      footer={
        <>
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 cursor-pointer"
          >
            Đóng
          </button>
          <button
            type="submit"
            disabled={saving}
            className="px-4 py-2 bg-primary-600 text-sm font-semibold rounded-md text-white hover:bg-primary-700 disabled:opacity-40 cursor-pointer"
          >
            {saving ? "Đang lưu..." : "Lưu hồ sơ"}
          </button>
        </>
      }
    >
      <div className="space-y-4">
        {/*
          Nói ngay từ đầu biểu mẫu: điền hay không điền đều không khóa ai ra khỏi hệ thống. Không
          nói thì người nhập dè dặt, sợ điền sai thì người ta mất chuyến.
        */}
        <p className="rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-gray-500">
          Hồ sơ này chỉ dùng để <b>xếp thứ tự</b> khi chọn người dẫn — không có ô nào ở đây chặn
          được phân công. Bỏ trống cũng không sao, người đó vẫn nằm trong danh sách chọn.
        </p>

        {/* Loại hình chuyên: chọn từ danh mục thật của tour, không gõ tay, để so khớp được. */}
        <div>
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
            Loại hình chuyên
          </label>
          <div className="flex flex-wrap gap-2">
            {categories.length === 0 && (
              <span className="text-xs text-gray-400">Chưa có danh mục nào.</span>
            )}

            {categories.map((item) => {
              const daChon = categoryIds.includes(item.id);

              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() =>
                    setCategoryIds((truoc) =>
                      truoc.includes(item.id)
                        ? truoc.filter((id) => id !== item.id)
                        : [...truoc, item.id],
                    )
                  }
                  className={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors cursor-pointer ${
                    daChon
                      ? "border-primary-600 bg-primary-600 text-white"
                      : "border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
                  }`}
                >
                  {item.name}
                </button>
              );
            })}
          </div>
          <span className="text-[10px] text-gray-400 mt-1.5 block">
            Khớp với loại hình của tour thì được cộng điểm khi xếp người.
          </span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
              Tuyến quen
            </label>
            <input
              type="text"
              value={regions}
              onChange={(e) => setRegions(e.target.value)}
              placeholder="Hạ Long, Ninh Bình, Cát Bà"
              className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
            />
            <span className="text-[10px] text-gray-400 mt-1 block">
              Cách nhau bằng dấu phẩy. Khớp với điểm đến của tour thì được cộng điểm.
            </span>
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
              Ngôn ngữ
            </label>
            <input
              type="text"
              value={languages}
              onChange={(e) => setLanguages(e.target.value)}
              placeholder="Tiếng Việt, Tiếng Anh"
              className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
            />
            {/*
              Nói thật là hiện chưa chấm điểm được: tour không có ô khai đoàn cần tiếng gì nên
              không có gì để so. Hiện ra để người xếp tự cân, hơn là bịa ra một tiêu chí.
            */}
            <span className="text-[10px] text-gray-400 mt-1 block">
              Hiện ra khi xếp người. Chưa chấm điểm vì tour chưa khai đoàn cần ngôn ngữ nào.
            </span>
          </div>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
            Sức dẫn tối đa (khách)
          </label>
          <input
            type="number"
            min={1}
            max={500}
            value={maxGroupSize}
            onChange={(e) => setMaxGroupSize(e.target.value)}
            placeholder="35"
            className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
          />
          <span className="text-[10px] text-gray-400 mt-1 block">
            Chỉ để nhắc khi đoàn đông hơn con số này. Không chặn — đoàn đông thì bạn xếp thêm
            người, hệ thống không quyết hộ.
          </span>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
            Ghi chú
          </label>
          <textarea
            rows={2}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Dẫn tuyến vịnh Bắc Bộ từ 2019."
            className="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-md bg-gray-50/50 focus:outline-none focus:border-primary-500"
          />
        </div>
      </div>
    </Modal>
  );
};

export default GuideProfileModal;
