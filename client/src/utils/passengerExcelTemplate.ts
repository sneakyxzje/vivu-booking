import { strToU8, zipSync } from "fflate";
import { fillPassengerSlots, passengerTypeLabels } from "./passengerImport.ts";
import type { ImportContext } from "./passengerImport.ts";

const escapeXml = (value: string) => value.replace(/&/g, "&amp;").replace(/</g, "&lt;")
  .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
const xml = (body: string) => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' + body;
const cell = (address: string, value: string, style: number) =>
  `<c r="${address}" s="${style}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(value)}</t></is></c>`;

/** Build the downloadable app template as OOXML, with no personal data or example passengers. */
export function passengerExcelTemplate(context: ImportContext): Uint8Array<ArrayBuffer> {
  const passengers = fillPassengerSlots([], context);
  if (!passengers.length || passengers.length > 50) throw new Error("Mẫu Excel hỗ trợ từ 1 đến 50 hành khách.");
  const lastRow = 6 + passengers.length;
  const date = context.departure_date?.slice(0, 10).split("-").reverse().join("/");
  const summary = `${passengers.length} hành khách  •  ${context.adult_count} người lớn / ${context.child_count} trẻ em / ${context.infant_count} em bé${date ? `  •  Khởi hành ${date}` : ""}`;
  const headers = ["Họ và tên *", "Loại khách *", "Giới tính", "Ngày sinh", "Loại giấy tờ", "Số giấy tờ", "Điện thoại", "Yêu cầu riêng", "Người đại diện"];
  const row = (number: number, height: number, content: string) => `<row r="${number}" ht="${height}" customHeight="1">${content}</row>`;
  const banner = (number: number, height: number, content: string, style: number) => row(number, height,
    Array.from({ length: 9 }, (_, i) => cell(`${String.fromCharCode(65 + i)}${number}`, i === 0 ? content : "", style)).join(""));
  const sheetRows = [
    banner(1, 46, "VIVU  /  DANH SÁCH HÀNH KHÁCH", 1),
    banner(2, 30, summary, 2),
    banner(3, 30, "01  Điền các ô bên dưới  →  02  Lưu file .xlsx  →  03  Nhập file và kiểm tra trên website", 3),
    banner(4, 32, "* Bắt buộc. Ngày sinh: dd/mm/yyyy. CCCD và điện thoại đã đặt dạng Text để giữ số 0 đầu. Chỉ chọn một người đại diện.", 3),
    row(5, 10, ""),
    row(6, 34, headers.map((header, i) => cell(`${String.fromCharCode(65 + i)}6`, header, 4)).join("")),
    ...passengers.map((passenger, index) => {
      const number = index + 7;
      const values = ["", passengerTypeLabels[passenger.type], "", "", passenger.type === "adult" ? "CCCD" : "", "", "", "", index === 0 ? "Có" : "Không"];
      return row(number, 34, values.map((value, i) => {
        // Alternating fills; the actual cell format preserves phone/identity text on entry.
        const base = i === 5 || i === 6 ? 7 : i === 3 ? 9 : 5;
        return cell(`${String.fromCharCode(65 + i)}${number}`, value, base + index % 2);
      }).join(""));
    }),
  ].join("");
  const validation = (column: string, values: string, prompt: string) =>
    `<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorStyle="stop" errorTitle="Chọn giá trị trong danh sách" error="Vui lòng dùng một lựa chọn có sẵn." promptTitle="Hướng dẫn điền" prompt="${escapeXml(prompt)}" sqref="${column}7:${column}${lastRow}"><formula1>"${values}"</formula1></dataValidation>`;
  const worksheet = xml(`<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetPr><tabColor rgb="FF0F766E"/><pageSetUpPr fitToPage="1"/></sheetPr>
    <dimension ref="A1:I${lastRow}"/>
    <sheetViews><sheetView showGridLines="0" zoomScale="85" workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A7" sqref="A7"/></sheetView></sheetViews>
    <sheetFormatPr defaultRowHeight="28"/>
    <cols>${[30, 18, 14, 17, 23, 25, 22, 38, 21].map((width, i) => `<col min="${i + 1}" max="${i + 1}" width="${width}" customWidth="1"${i === 5 || i === 6 ? ' style="7"' : ""}/>`).join("")}</cols>
    <sheetData>${sheetRows}</sheetData>
    <autoFilter ref="A6:I${lastRow}"/>
    <mergeCells count="4">${[1, 2, 3, 4].map(i => `<mergeCell ref="A${i}:I${i}"/>`).join("")}</mergeCells>
    <dataValidations count="4">${validation("B", "Người lớn,Trẻ em,Em bé", "Giữ đúng số lượng từng loại khách của đơn.")}${validation("C", "Nam,Nữ,Khác", "Có thể để trống nếu chưa có thông tin.")}${validation("E", "CCCD,CMND,Hộ chiếu,Giấy khai sinh", "Chỉ khai giấy tờ cho người lớn.")}${validation("I", "Có,Không", "Chỉ chọn Có cho một người đại diện.")}</dataValidations>
    <printOptions horizontalCentered="1"/>
    <pageMargins left="0.25" right="0.25" top="0.35" bottom="0.35" header="0.15" footer="0.15"/>
    <pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>
    <headerFooter><oddFooter>&amp;LViVu • Danh sách hành khách&amp;RTrang &amp;P / &amp;N</oddFooter></headerFooter>
  </worksheet>`);

  const bodyStyle = (fill: number, format: number) => `<xf numFmtId="${format}" fontId="0" fillId="${fill}" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1" indent="1"/></xf>`;
  const styles = xml(`<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>
    <fonts count="5">
      <font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/></font>
      <font><b/><sz val="22"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
      <font><b/><sz val="11"/><color rgb="FF0F766E"/><name val="Calibri"/></font>
      <font><sz val="11"/><color rgb="FF475569"/><name val="Calibri"/></font>
      <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    </fonts>
    <fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>
      ${["FF123047", "FFE8F5F1", "FF0F766E", "FFF1F5F9"].map(color => `<fill><patternFill patternType="solid"><fgColor rgb="${color}"/><bgColor indexed="64"/></patternFill></fill>`).join("")}
    </fills>
    <borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>${["left", "right", "top", "bottom"].map(side => `<${side} style="hair"><color rgb="FFD7E2E8"/></${side}>`).join("")}<diagonal/></border></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="11">
      <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
      <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1" applyFill="1"><alignment vertical="center" indent="1"/></xf>
      <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyAlignment="1" applyFill="1"><alignment vertical="center" indent="2"/></xf>
      <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1" indent="1"/></xf>
      <xf numFmtId="0" fontId="4" fillId="4" borderId="0" xfId="0" applyAlignment="1" applyFill="1"><alignment vertical="center" wrapText="1" indent="1"/></xf>
      ${[0, 49, 164].map(format => [0, 5].map(fill => bodyStyle(fill, format)).join("")).join("")}
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
  </styleSheet>`);

  const files: Record<string, string> = {
    "[Content_Types].xml": xml('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>'),
    "_rels/.rels": xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'),
    "xl/workbook.xml": xml(`<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Hành khách" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">'Hành khách'!$A$1:$I$${lastRow}</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">'Hành khách'!$6:$6</definedName></definedNames></workbook>`),
    "xl/_rels/workbook.xml.rels": xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'),
    "xl/worksheets/sheet1.xml": worksheet,
    "xl/styles.xml": styles,
  };
  return new Uint8Array(zipSync(Object.fromEntries(Object.entries(files).map(([name, content]) => [name, strToU8(content)])), { level: 6 }));
}
