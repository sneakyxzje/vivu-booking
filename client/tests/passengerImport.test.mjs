import test from 'node:test';
import assert from 'node:assert/strict';
import { zipSync, strToU8, unzipSync, strFromU8 } from 'fflate';
import { DOMParser } from '@xmldom/xmldom';
import { previewPassengers, fillPassengerSlots, passengerCsvTemplate } from '../src/utils/passengerImport.ts';
import { readPassengerFile, MAX_IMPORT_BYTES } from '../src/utils/passengerImportFile.ts';
import { passengerExcelTemplate } from '../src/utils/passengerExcelTemplate.ts';

globalThis.DOMParser = DOMParser;
const context = { adult_count: 1, child_count: 1, infant_count: 0, departure_date: '2026-10-20' };
const today = new Date('2026-09-26T12:00:00Z');
const preview = rows => previewPassengers({ name: 'test', rows }, context, today);
const office = (name, files) => new File([zipSync(Object.fromEntries(Object.entries(files).map(([key, value]) => [key, strToU8(value)])))], name);

test('styled Excel template preserves purchased slots and imports after names are entered', async () => {
  const bytes = passengerExcelTemplate(context);
  const [table] = await readPassengerFile(new File([bytes], 'mau.xlsx'));
  assert.equal(table.rows[5][0], 'Họ và tên *');
  assert.equal(table.rows[6][1], 'Người lớn');
  assert.equal(table.rows[7][1], 'Trẻ em');
  table.rows[6][0] = 'Nguyễn An';
  table.rows[7][0] = 'Bé An';
  table.rows[6][5] = '001234567890';
  table.rows[6][6] = '0901234567';
  const result = previewPassengers(table, context, today);
  assert.deepEqual(result.errors, []);
  assert.equal(result.passengers.length, 2);
  assert.equal(result.passengers[0].identity_number, '001234567890');
  const files = unzipSync(bytes);
  const sheet = new DOMParser().parseFromString(strFromU8(files['xl/worksheets/sheet1.xml']), 'application/xml');
  assert.equal(sheet.getElementsByTagName('pane')[0].getAttribute('state'), 'frozen');
  assert.equal(sheet.getElementsByTagName('dataValidation').length, 4);
  const styles = new DOMParser().parseFromString(strFromU8(files['xl/styles.xml']), 'application/xml');
  const formats = styles.getElementsByTagName('cellXfs')[0].getElementsByTagName('xf');
  for (const address of ['F7', 'G7', 'F8', 'G8']) {
    const cell = Array.from(sheet.getElementsByTagName('c')).find(c => c.getAttribute('r') === address);
    assert.equal(formats[Number(cell.getAttribute('s'))].getAttribute('numFmtId'), '49');
  }
});

test('CSV UTF-8 keeps leading zeros, quoted commas, and multiline notes', async () => {
  const file = new File(['\uFEFFHọ và tên,Loại khách,Ngày sinh,Số giấy tờ,Điện thoại,Yêu cầu riêng\r\n"Nguyễn, An",Người lớn,01/02/1990,001234567890,0901234567,"Ăn chay, dị ứng\nkhông hải sản"'], 'passengers.csv');
  const tables = await readPassengerFile(file);
  const result = previewPassengers(tables[0], context, today);
  assert.deepEqual(result.errors, []);
  assert.equal(result.passengers[0].name, 'Nguyễn, An');
  assert.equal(result.passengers[0].identity_number, '001234567890');
  assert.equal(result.passengers[0].phone, '0901234567');
  assert.equal(result.passengers[0].date_of_birth, '1990-02-01');
  assert.match(result.passengers[0].special_request, /\n/);
});

test('CSV accepts semicolons without converting identifiers to numbers', async () => {
  const [table] = await readPassengerFile(new File(['Họ tên;Loại khách;CCCD\nAn;adult;000123'], 'list.csv'));
  assert.equal(previewPassengers(table, context, today).passengers[0].identity_number, '000123');
});

test('valid child-first input is placed into matching purchased slots', () => {
  const result = preview([['Họ tên', 'Loại khách', 'Ngày sinh'], ['Bé An', 'Trẻ em', '01/01/2020'], ['Bố An', 'Người lớn', '01/01/1990']]);
  assert.deepEqual(result.errors, []);
  const rows = fillPassengerSlots(result.passengers, context);
  assert.equal(rows[0].name, 'Bố An');
  assert.equal(rows[0].is_contact, true);
  assert.equal(rows[1].name, 'Bé An');
  assert.equal(rows[1].type, 'child');
  assert.equal(result.passengers[0].name, 'Bé An');
});

test('rejects invalid dates, mismatched age, duplicate IDs and excess passenger types', () => {
  const result = preview([
    ['Họ tên', 'Loại khách', 'Ngày sinh', 'CCCD'],
    ['An', 'adult', '31/02/1990', '001'],
    ['Bình', 'adult', '01/01/2020', '001'],
  ]);
  assert.ok(result.errors.some(e => e.includes('Ngày sinh không hợp lệ')));
  assert.ok(result.errors.some(e => e.includes('không khớp loại khách')));
  assert.ok(result.errors.some(e => e.includes('trùng với dòng 2')));
  assert.ok(result.errors.some(e => e.includes('đơn chỉ đặt 1')));
});

test('requires name and type headers and rejects duplicate mapped columns', () => {
  assert.ok(preview([['Name'], ['An']]).errors.length);
  assert.ok(preview([['Name', 'Họ tên', 'Type'], ['An', 'An', 'adult']]).errors.some(e => e.includes('bị lặp')));
});

test('rejects unknown enums and multiple contacts; blank rows are harmless', () => {
  const result = preview([
    ['name', 'type', 'gender', 'is_contact'], [],
    ['An', 'adult', 'unknown', 'Có'], ['Bé An', 'child', '', 'Có'],
  ]);
  assert.equal(result.passengers.length, 2);
  assert.ok(result.errors.some(e => e.includes('Dòng 3') && e.includes('Giới tính')));
  assert.ok(result.errors.some(e => e.includes('một người đại diện')));
});

test('missing names are errors, not silently skipped; more than 50 rows rejected', () => {
  assert.ok(preview([['name', 'type'], ['', 'adult']]).errors.some(e => e.includes('Họ tên')));
  assert.ok(preview([['name', 'type'], ...Array.from({ length: 51 }, () => ['An', 'adult'])]).errors.some(e => e.includes('50')));
});

test('CSV template has purchased categories and imports after names are filled', async () => {
  const [table] = await readPassengerFile(new File([passengerCsvTemplate(context)], 'mau.csv'));
  table.rows[1][0] = 'An'; table.rows[2][0] = 'Bé An';
  assert.deepEqual(previewPassengers(table, context, today).errors, []);
});

const word = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>' +
  '<w:tr><w:tc><w:p><w:r><w:t>Họ và tên</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Loại khách</w:t></w:r></w:p></w:tc></w:tr>' +
  '<w:tr><w:tc><w:p><w:r><w:t>Nguyễn </w:t></w:r><w:r><w:t>Văn An</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Người lớn</w:t></w:r></w:p></w:tc></w:tr>' +
  '</w:tbl></w:body></w:document>';
test('DOCX reads table cells and joins formatted Word text runs', async () => {
  const [table] = await readPassengerFile(office('list.docx', { 'word/document.xml': word }));
  const result = previewPassengers(table, context, today);
  assert.deepEqual(result.errors, []);
  assert.equal(result.passengers[0].name, 'Nguyễn Văn An');
});

test('DOCX with DTD or without a table is rejected', async () => {
  await assert.rejects(readPassengerFile(office('list.docx', { 'word/document.xml': '<!DOCTYPE document>' + word })), /XML/);
  await assert.rejects(readPassengerFile(office('list.docx', { 'word/document.xml': '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Some prose</w:t></w:r></w:p></w:body></w:document>' })), /bảng hành khách/);
});

const excel = (phoneType = 'inlineStr') => office('list.xlsx', {
  '[Content_Types].xml': '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
  'xl/workbook.xml': '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Khách" sheetId="1" r:id="rId1"/></sheets></workbook>',
  'xl/_rels/workbook.xml.rels': '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
  'xl/styles.xml': '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs></styleSheet>',
  'xl/worksheets/sheet1.xml': '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:D2"/><sheetData>' +
    '<row r="1"><c r="A1" t="inlineStr"><is><t>Họ tên</t></is></c><c r="B1" t="inlineStr"><is><t>Loại khách</t></is></c><c r="C1" t="inlineStr"><is><t>Điện thoại</t></is></c><c r="D1" t="inlineStr"><is><t>Ngày sinh</t></is></c></row>' +
    '<row r="2"><c r="A2" t="inlineStr"><is><t>Nguyễn An</t></is></c><c r="B2" t="inlineStr"><is><t>adult</t></is></c>' +
    (phoneType === 'inlineStr' ? '<c r="C2" t="inlineStr"><is><t>0901234567</t></is></c>' : '<c r="C2"><v>901234567</v></c>') +
    '<c r="D2" s="1"><v>32874</v></c></row></sheetData></worksheet>',
});
test('real XLSX archive reads text identifiers and Excel date serials', async () => {
  const [table] = await readPassengerFile(excel());
  const result = previewPassengers(table, context, today);
  assert.deepEqual(result.errors, []);
  assert.equal(result.passengers[0].phone, '0901234567');
  assert.equal(result.passengers[0].date_of_birth, '1990-01-01');
});

test('numeric Excel phone is flagged instead of inventing lost leading zeros', async () => {
  const [table] = await readPassengerFile(excel('number'));
  assert.ok(previewPassengers(table, context, today).errors.some(e => e.includes('ô số trong Excel')));
});

test('rejects unsupported, corrupted, empty, oversized and expanded archive files', async () => {
  await assert.rejects(readPassengerFile(new File(['test'], 'list.pdf')), /Chọn Excel/);
  await assert.rejects(readPassengerFile(new File([], 'list.csv')), /trống/);
  await assert.rejects(readPassengerFile(new File(['not zip'], 'list.xlsx')));
  await assert.rejects(readPassengerFile(new File([new Uint8Array(MAX_IMPORT_BYTES + 1)], 'list.csv')), /5 MB/);
  await assert.rejects(readPassengerFile(office('list.docx', { 'word/document.xml': 'a'.repeat(21 * 1024 * 1024) })), /quá nhiều nội dung/);
});
