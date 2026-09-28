/**
 * Các tiện ích kiểm tra tính hợp lệ (Validation) dữ liệu phía Client.
 */

/**
 * Kiểm tra định dạng Email chuẩn.
 * Ví dụ hợp lệ: user@gmail.com, name.surname@company.com.vn
 */
export const validateEmail = (email: string): boolean => {
  if (!email) return false;
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  return emailRegex.test(email.trim());
};

/**
 * Kiểm tra số điện thoại hợp lệ tại Việt Nam.
 * Chấp nhận các đầu số 10 chữ số (03, 05, 07, 08, 09) hoặc dạng quốc tế +84.
 */
export const validatePhone = (phone: string): boolean => {
  if (!phone) return false;
  const cleanPhone = phone.trim().replace(/\s+/g, "");
  const phoneRegex = /^(0|\+84)(3|5|7|8|9)[0-9]{8}$/;
  return phoneRegex.test(cleanPhone);
};

/**
 * Kiểm tra chuyến đi có ít nhất 1 người lớn hay không.
 */
export const validateHasAdultPassenger = (adultCount: number): boolean => {
  return Number.isInteger(adultCount) && adultCount >= 1;
};
