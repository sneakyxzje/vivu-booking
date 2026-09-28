-- ViVu defense demo snapshot. Synthetic identities; restore with demo:restore.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;




CREATE TABLE `ai_chat_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `conversation_token` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `role` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_calls` json DEFAULT NULL,
  `prompt_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `completion_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `ai_knowledge_chunks` (
  `id` bigint UNSIGNED NOT NULL,
  `chunk_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `embedding` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dimensions` smallint UNSIGNED DEFAULT NULL,
  `indexed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `bookings` (
  `id` bigint UNSIGNED NOT NULL,
  `public_token` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `guest_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tour_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departure_date` datetime NOT NULL,
  `guests` int UNSIGNED NOT NULL,
  `seats` int UNSIGNED NOT NULL DEFAULT '0',
  `adult_count` int UNSIGNED NOT NULL DEFAULT '1',
  `child_count` int UNSIGNED NOT NULL DEFAULT '0',
  `infant_count` int UNSIGNED NOT NULL DEFAULT '0',
  `adult_price` decimal(12,2) DEFAULT NULL,
  `child_price` decimal(12,2) DEFAULT NULL,
  `infant_price` decimal(12,2) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `discount_code_id` bigint UNSIGNED DEFAULT NULL,
  `discount_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `transfer_count` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `split_from_booking_id` bigint UNSIGNED DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `departure_reminder_sent_at` datetime DEFAULT NULL,
  `balance_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `balance_final_notice_at` timestamp NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `seats_released` tinyint(1) NOT NULL DEFAULT '1',
  `seats_released_at` datetime DEFAULT NULL,
  `seats_released_by` bigint UNSIGNED DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT NULL,
  `refund_bank_account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_bank_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_account_holder` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_plan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_policy_id` bigint UNSIGNED DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `group_booking_request_id` bigint UNSIGNED DEFAULT NULL,
  `vnpay_transaction_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `bookings` (`id`, `public_token`, `tour_id`, `customer_id`, `guest_id`, `tour_schedule_id`, `customer_name`, `customer_email`, `customer_phone`, `departure_date`, `guests`, `seats`, `adult_count`, `child_count`, `infant_count`, `adult_price`, `child_price`, `infant_price`, `total_amount`, `discount_code_id`, `discount_code`, `discount_amount`, `status`, `completed_at`, `transfer_count`, `split_from_booking_id`, `expires_at`, `departure_reminder_sent_at`, `balance_reminder_sent_at`, `balance_final_notice_at`, `note`, `cancel_reason`, `cancel_type`, `cancelled_at`, `cancelled_by`, `seats_released`, `seats_released_at`, `seats_released_by`, `refund_amount`, `refund_bank_account`, `refund_bank_name`, `refund_account_holder`, `cancellation_plan`, `cancellation_policy_id`, `terms_accepted_at`, `group_booking_request_id`, `vnpay_transaction_no`, `paid_at`, `confirmed_at`, `created_at`, `updated_at`) VALUES
(1, '190a2848-b37f-523f-a0bf-93dd5bd1474d', 1, 3, NULL, 2, 'Thông tin demo 1', 'customer@example.test', '0900000001', '2026-08-19 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-05 02:21:00', '2026-04-05 02:21:00', '2026-04-05 02:15:00', '2026-04-05 02:21:00'),
(2, '40a06475-4246-5d25-9517-0d70867320bc', 1, 17, NULL, 2, 'Thông tin demo 2', 'customer17@example.test', '0900000002', '2026-08-19 05:30:00', 3, 0, 2, 1, 0, NULL, NULL, NULL, 8613000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-07 03:21:00', '2026-05-07 03:21:00', '2026-05-07 03:15:00', '2026-05-07 03:21:00'),
(3, 'b7ff6cbc-248a-518a-93ed-025b456d172f', 1, 18, NULL, 2, 'Thông tin demo 3', 'customer18@example.test', '0900000003', '2026-08-19 05:30:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 3190000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-09 04:21:00', '2026-06-09 04:15:00', '2026-06-09 04:21:00'),
(4, '9ad334a6-982e-5b00-8f87-739b9ed343d1', 1, 19, NULL, 2, 'Thông tin demo 4', 'customer19@example.test', '0900000004', '2026-08-19 05:30:00', 4, 0, 3, 1, 0, NULL, NULL, NULL, 11803000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-11 05:21:00', '2026-07-11 05:21:00', '2026-07-11 05:15:00', '2026-07-11 05:21:00'),
(5, '1eca1799-054a-5201-85c2-b30a2c62345f', 1, 3, NULL, 2, 'Thông tin demo 5', 'customer@example.test', '0900000005', '2026-08-19 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-13 06:21:00', '2026-08-13 06:21:00', '2026-08-13 06:15:00', '2026-08-13 06:21:00'),
(6, '9b29cf66-d583-5abc-9cfd-76a3937a57c1', 1, 17, NULL, 3, 'Thông tin demo 6', 'customer17@example.test', '0900000006', '2026-10-05 05:30:00', 3, 0, 2, 1, 0, NULL, NULL, NULL, 8613000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-15 07:21:00', '2026-09-15 07:21:00', '2026-09-15 07:15:00', '2026-09-15 07:21:00'),
(7, 'eab7a87d-27d0-5c37-bfd1-ce953cf6f161', 1, 18, NULL, 3, 'Thông tin demo 7', 'customer18@example.test', '0900000007', '2026-10-05 05:30:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 3190000.00, NULL, NULL, 0.00, 'pending', NULL, 0, NULL, '2026-09-29 19:36:50', NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-17 08:15:00', '2026-09-17 08:21:00'),
(8, '62262d38-0e0b-5385-bcc2-e4b82ddc48fc', 1, 19, NULL, 2, 'Thông tin demo 8', 'customer19@example.test', '0900000008', '2026-08-19 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'cancelled', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, 1, '2026-08-21 16:15:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 09:15:00', '2026-08-19 09:21:00'),
(9, '0d7625f2-034a-5c8c-9698-d1ffe905e30f', 2, 3, NULL, 7, 'Thông tin demo 9', 'customer@example.test', '0900000009', '2026-08-09 08:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 7780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-21 02:21:00', '2026-05-21 02:21:00', '2026-05-21 02:15:00', '2026-05-21 02:21:00'),
(10, '8ec127ce-48b4-5e77-aec6-9a766d11e3fa', 2, 17, NULL, 7, 'Thông tin demo 10', 'customer17@example.test', '0900000010', '2026-08-09 08:00:00', 4, 0, 2, 2, 0, NULL, NULL, NULL, 13226000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-23 03:21:00', '2026-07-23 03:21:00', '2026-07-23 03:15:00', '2026-07-23 03:21:00'),
(11, '43941393-40e5-5178-a988-f29c7b093aee', 2, 18, NULL, 8, 'Thông tin demo 11', 'customer18@example.test', '0900000011', '2026-09-30 08:00:00', 2, 0, 1, 1, 0, NULL, NULL, NULL, 6613000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-05 04:21:00', '2026-09-05 04:15:00', '2026-09-05 04:21:00'),
(12, 'fed82d0d-6008-514d-89e8-1e956b3c18ff', 2, 19, NULL, 7, 'Thông tin demo 12', 'customer19@example.test', '0900000012', '2026-08-09 08:00:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 3890000.00, NULL, NULL, 0.00, 'cancelled', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, 1, '2026-06-09 12:15:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-07 05:15:00', '2026-06-07 05:21:00'),
(13, '0f5a972d-edca-53b5-8ffa-953f80712d89', 3, 3, NULL, 12, 'Thông tin demo 13', 'customer@example.test', '0900000013', '2026-09-06 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-09 06:21:00', '2026-08-09 06:21:00', '2026-08-09 06:15:00', '2026-08-09 06:21:00'),
(14, '17b9f4b3-f364-5538-96ff-857cb2a67541', 3, 17, NULL, 14, 'Thông tin demo 14', 'customer17@example.test', '0900000014', '2026-10-07 21:00:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 1890000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-11 07:21:00', '2026-09-11 07:21:00', '2026-09-11 07:15:00', '2026-09-11 07:21:00'),
(15, 'f2d7a1aa-ad99-53c4-8f31-6e55169774fc', 2, 3, NULL, 7, 'Thông tin demo 15', 'customer@example.test', '0900000015', '2026-08-09 08:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 7780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 03:30:00', '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(16, '5c9f655a-f693-520e-89c9-820e652709db', 3, 19, NULL, 12, 'Thông tin demo 16', 'customer19@example.test', '0900000016', '2026-09-06 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'cancelled', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', 'Dữ liệu minh họa bảo vệ đồ án.', 'by_customer', '2026-09-24 15:00:00', NULL, 1, '2026-09-24 15:00:00', NULL, 3402000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-04 08:00:00', NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(17, '1183ab17-31ab-52ac-a877-d2092c6b3941', 1, 3, NULL, 1, 'Thông tin demo 17', 'customer@example.test', '0900000017', '2026-07-15 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'completed', '2026-07-17 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-05 22:38:00', '2026-07-05 22:38:00', '2026-07-05 22:30:00', '2026-07-05 22:38:00'),
(18, 'b4a6582a-109a-5d01-b8bb-4fe7a5247215', 1, 17, NULL, 1, 'Thông tin demo 18', 'customer17@example.test', '0900000018', '2026-07-15 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'completed', '2026-07-17 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-04 22:38:00', '2026-07-04 22:38:00', '2026-07-04 22:30:00', '2026-07-04 22:38:00'),
(19, '356402e6-bfa2-5fc0-98b5-d97275d97732', 1, 18, NULL, 1, 'Thông tin demo 19', 'customer18@example.test', '0900000019', '2026-07-15 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'completed', '2026-07-17 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-03 22:38:00', '2026-07-03 22:38:00', '2026-07-03 22:30:00', '2026-07-03 22:38:00'),
(20, 'f916b9dd-f8ce-5493-8b87-b03dc18fb9d8', 1, 19, NULL, 1, 'Thông tin demo 20', 'customer19@example.test', '0900000020', '2026-07-15 05:30:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 6380000.00, NULL, NULL, 0.00, 'completed', '2026-07-17 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-02 22:38:00', '2026-07-02 22:38:00', '2026-07-02 22:30:00', '2026-07-02 22:38:00'),
(21, '9810c8e6-2f8b-504b-84f8-14c349a5b657', 2, 3, NULL, 7, 'Thông tin demo 21', 'customer@example.test', '0900000021', '2026-08-09 08:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 7780000.00, NULL, NULL, 0.00, 'completed', '2026-08-12 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-27 01:08:00', '2026-07-27 01:08:00', '2026-07-27 01:00:00', '2026-07-27 01:08:00'),
(22, '40c95972-a277-545e-be07-250acb253213', 3, 17, NULL, 12, 'Thông tin demo 22', 'customer17@example.test', '0900000022', '2026-09-06 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'completed', '2026-09-07 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-28 14:08:00', '2026-08-28 14:08:00', '2026-08-28 14:00:00', '2026-08-28 14:08:00'),
(23, 'b63c0a32-f588-5b02-b9e6-cfc0d737c41b', 3, 18, NULL, 12, 'Thông tin demo 23', 'customer18@example.test', '0900000023', '2026-09-06 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'completed', '2026-09-07 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-27 14:08:00', '2026-08-27 14:08:00', '2026-08-27 14:00:00', '2026-08-27 14:08:00'),
(24, 'e45d859c-6b9a-574f-b6db-912ff93d2527', 3, 19, NULL, 12, 'Thông tin demo 24', 'customer19@example.test', '0900000024', '2026-09-06 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'completed', '2026-09-07 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-26 14:08:00', '2026-08-26 14:08:00', '2026-08-26 14:00:00', '2026-08-26 14:08:00'),
(25, '359d1e4e-2a10-52fa-91d1-d7b32bdb614a', 3, 3, NULL, 12, 'Thông tin demo 25', 'customer@example.test', '0900000025', '2026-09-06 21:00:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 1890000.00, NULL, NULL, 0.00, 'completed', '2026-09-07 11:00:00', 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-25 14:08:00', '2026-08-25 14:08:00', '2026-08-25 14:00:00', '2026-08-25 14:08:00'),
(26, '828c5f74-6085-5f12-ba00-404106706095', 3, 17, NULL, 13, 'Thông tin demo 26', 'customer17@example.test', '0900000026', '2026-10-01 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-18 14:08:00', '2026-09-18 14:08:00', '2026-09-18 14:00:00', '2026-09-18 14:08:00'),
(27, '5d60bef1-0abc-5091-9a06-d13c58e1ecc4', 3, 18, NULL, 13, 'Thông tin demo 27', 'customer18@example.test', '0900000027', '2026-10-01 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-22 14:08:00', '2026-09-22 14:08:00', '2026-09-22 14:00:00', '2026-09-22 14:08:00'),
(28, 'a8d0c2ea-6c01-5059-8f5d-d00983157d1d', 3, 19, NULL, 13, 'Thông tin demo 28', 'customer19@example.test', '0900000028', '2026-10-01 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-21 14:08:00', '2026-09-21 14:08:00', '2026-09-21 14:00:00', '2026-09-21 14:08:00'),
(29, '2fb03b80-1e36-58a1-afb4-c918b882b4f4', 3, 3, NULL, 13, 'Thông tin demo 29', 'customer@example.test', '0900000029', '2026-10-01 21:00:00', 2, 0, 2, 0, 0, NULL, NULL, NULL, 3780000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 14:08:00', '2026-09-20 14:08:00', '2026-09-20 14:00:00', '2026-09-20 14:08:00'),
(30, 'ec99120e-1292-5d04-a332-e0e8269fdf63', 3, 17, NULL, 13, 'Thông tin demo 30', 'customer17@example.test', '0900000030', '2026-10-01 21:00:00', 1, 0, 1, 0, 0, NULL, NULL, NULL, 1890000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 14:08:00', '2026-09-19 14:08:00', '2026-09-19 14:00:00', '2026-09-19 14:08:00'),
(31, 'bf4dfde6-5c91-5dc5-887c-e5c40239f1c1', 2, NULL, NULL, 10, 'Thông tin demo 31', 'bookings-31@example.test', '0900000031', '2026-11-05 08:00:00', 12, 12, 12, 0, 0, NULL, NULL, NULL, 19800000.00, NULL, NULL, 0.00, 'confirmed', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 3, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50', '2026-09-28 12:36:50');



CREATE TABLE `booking_audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `actor_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `booking_change_proposals` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `admin_id` bigint UNSIGNED NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposed_date` datetime DEFAULT NULL,
  `response_deadline` datetime NOT NULL,
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `customer_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `from_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `to_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `schedule_snapshot` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `booking_change_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `estimated_refund` decimal(12,2) DEFAULT NULL,
  `estimated_refund_percent` tinyint UNSIGNED DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_by` bigint UNSIGNED DEFAULT NULL,
  `requested_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `booking_checkins` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `tour_itinerary_id` bigint UNSIGNED NOT NULL,
  `guide_id` bigint UNSIGNED DEFAULT NULL,
  `present` tinyint(1) NOT NULL DEFAULT '1',
  `checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `booking_contracts` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `contract_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_at` datetime NOT NULL,
  `issued_by` bigint UNSIGNED DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `signed_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `booking_passengers` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('adult','child','infant') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'adult',
  `date_of_birth` date DEFAULT NULL,
  `identity_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_request` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_contact` tinyint(1) NOT NULL DEFAULT '0',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `booking_passengers` (`id`, `booking_id`, `name`, `gender`, `type`, `date_of_birth`, `identity_number`, `id_type`, `nationality`, `phone`, `special_request`, `is_contact`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, 'Hành khách Demo 1', NULL, 'adult', '1990-01-01', '000000000001', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(2, 4, 'Hành khách Demo 2', NULL, 'adult', '1990-01-01', '000000000002', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(3, 4, 'Hành khách Demo 3', NULL, 'child', '2018-01-01', '000000000003', NULL, NULL, NULL, NULL, 0, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(4, 7, 'Hành khách Demo 4', NULL, 'adult', '1990-01-01', '000000000004', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(5, 10, 'Hành khách Demo 5', NULL, 'adult', '1990-01-01', '000000000005', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(6, 10, 'Hành khách Demo 6', NULL, 'child', '2018-01-01', '000000000006', NULL, NULL, NULL, NULL, 0, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(7, 13, 'Hành khách Demo 7', NULL, 'adult', '1990-01-01', '000000000007', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(8, 17, 'Hành khách Demo 8', NULL, 'adult', '1990-01-01', '000000000008', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(9, 17, 'Hành khách Demo 9', NULL, 'adult', '1990-01-01', '000000000009', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(10, 18, 'Hành khách Demo 10', NULL, 'adult', '1990-01-01', '000000000010', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(11, 18, 'Hành khách Demo 11', NULL, 'adult', '1990-01-01', '000000000011', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(12, 19, 'Hành khách Demo 12', NULL, 'adult', '1990-01-01', '000000000012', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(13, 19, 'Hành khách Demo 13', NULL, 'adult', '1990-01-01', '000000000013', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(14, 20, 'Hành khách Demo 14', NULL, 'adult', '1990-01-01', '000000000014', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(15, 20, 'Hành khách Demo 15', NULL, 'adult', '1990-01-01', '000000000015', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(16, 21, 'Hành khách Demo 16', NULL, 'adult', '1990-01-01', '000000000016', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(17, 21, 'Hành khách Demo 17', NULL, 'adult', '1990-01-01', '000000000017', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(18, 22, 'Hành khách Demo 18', NULL, 'adult', '1990-01-01', '000000000018', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(19, 22, 'Hành khách Demo 19', NULL, 'adult', '1990-01-01', '000000000019', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(20, 23, 'Hành khách Demo 20', NULL, 'adult', '1990-01-01', '000000000020', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(21, 23, 'Hành khách Demo 21', NULL, 'adult', '1990-01-01', '000000000021', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(22, 24, 'Hành khách Demo 22', NULL, 'adult', '1990-01-01', '000000000022', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(23, 24, 'Hành khách Demo 23', NULL, 'adult', '1990-01-01', '000000000023', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(24, 25, 'Hành khách Demo 24', NULL, 'adult', '1990-01-01', '000000000024', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(25, 26, 'Hành khách Demo 25', NULL, 'adult', '1990-01-01', '000000000025', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(26, 26, 'Hành khách Demo 26', NULL, 'adult', '1990-01-01', '000000000026', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(27, 27, 'Hành khách Demo 27', NULL, 'adult', '1990-01-01', '000000000027', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(28, 27, 'Hành khách Demo 28', NULL, 'adult', '1990-01-01', '000000000028', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(29, 28, 'Hành khách Demo 29', NULL, 'adult', '1990-01-01', '000000000029', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(30, 28, 'Hành khách Demo 30', NULL, 'adult', '1990-01-01', '000000000030', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(31, 29, 'Hành khách Demo 31', NULL, 'adult', '1990-01-01', '000000000031', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(32, 29, 'Hành khách Demo 32', NULL, 'adult', '1990-01-01', '000000000032', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(33, 30, 'Hành khách Demo 33', NULL, 'adult', '1990-01-01', '000000000033', NULL, NULL, NULL, NULL, 0, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50');



CREATE TABLE `booking_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `booking_surcharge_id` bigint UNSIGNED DEFAULT NULL,
  `kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `recorded_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `booking_payments` (`id`, `booking_id`, `booking_surcharge_id`, `kind`, `amount`, `method`, `reference`, `note`, `paid_at`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'balance', 6380000.00, 'gateway', 'DEMO-1', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-04-05 09:21:00', NULL, '2026-04-05 02:21:00', '2026-04-05 02:21:00'),
(2, 2, NULL, 'balance', 8613000.00, 'gateway', 'DEMO-2', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-05-07 10:21:00', NULL, '2026-05-07 03:21:00', '2026-05-07 03:21:00'),
(3, 3, NULL, 'balance', 3190000.00, 'bank_transfer', 'DEMO-3', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-06-09 11:21:00', NULL, '2026-06-09 04:21:00', '2026-06-09 04:21:00'),
(4, 4, NULL, 'balance', 11803000.00, 'gateway', 'DEMO-4', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-11 12:21:00', NULL, '2026-07-11 05:21:00', '2026-07-11 05:21:00'),
(5, 5, NULL, 'balance', 6380000.00, 'gateway', 'DEMO-5', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-13 13:21:00', NULL, '2026-08-13 06:21:00', '2026-08-13 06:21:00'),
(6, 6, NULL, 'balance', 8613000.00, 'gateway', 'DEMO-6', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-15 14:21:00', NULL, '2026-09-15 07:21:00', '2026-09-15 07:21:00'),
(7, 9, NULL, 'balance', 7780000.00, 'gateway', 'DEMO-7', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-05-21 09:21:00', NULL, '2026-05-21 02:21:00', '2026-05-21 02:21:00'),
(8, 10, NULL, 'balance', 13226000.00, 'gateway', 'DEMO-8', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-23 10:21:00', NULL, '2026-07-23 03:21:00', '2026-07-23 03:21:00'),
(9, 11, NULL, 'balance', 6613000.00, 'bank_transfer', 'DEMO-9', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-05 11:21:00', NULL, '2026-09-05 04:21:00', '2026-09-05 04:21:00'),
(10, 13, NULL, 'balance', 3780000.00, 'gateway', 'DEMO-10', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-09 13:21:00', NULL, '2026-08-09 06:21:00', '2026-08-09 06:21:00'),
(11, 14, NULL, 'balance', 1890000.00, 'gateway', 'DEMO-11', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-11 14:21:00', NULL, '2026-09-11 07:21:00', '2026-09-11 07:21:00'),
(12, 15, NULL, 'balance', 4668000.00, 'bank_transfer', 'DEMO-12', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-19 10:30:00', NULL, '2026-09-19 03:30:00', '2026-09-19 03:30:00'),
(13, 16, NULL, 'balance', 3780000.00, 'gateway', 'DEMO-13', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-04 15:00:00', NULL, '2026-09-04 08:00:00', '2026-09-04 08:00:00'),
(14, 17, NULL, 'balance', 6380000.00, 'gateway', 'DEMO-14', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-06 05:36:00', NULL, '2026-07-05 22:36:00', '2026-07-05 22:36:00'),
(15, 18, NULL, 'balance', 6380000.00, 'bank_transfer', 'DEMO-15', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-05 05:36:00', NULL, '2026-07-04 22:36:00', '2026-07-04 22:36:00'),
(16, 19, NULL, 'balance', 6380000.00, 'gateway', 'DEMO-16', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-04 05:36:00', NULL, '2026-07-03 22:36:00', '2026-07-03 22:36:00'),
(17, 20, NULL, 'balance', 6380000.00, 'bank_transfer', 'DEMO-17', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-03 05:36:00', NULL, '2026-07-02 22:36:00', '2026-07-02 22:36:00'),
(18, 21, NULL, 'balance', 7780000.00, 'gateway', 'DEMO-18', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-07-27 08:06:00', NULL, '2026-07-27 01:06:00', '2026-07-27 01:06:00'),
(19, 22, NULL, 'balance', 3780000.00, 'bank_transfer', 'DEMO-19', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-28 21:06:00', NULL, '2026-08-28 14:06:00', '2026-08-28 14:06:00'),
(20, 23, NULL, 'balance', 3780000.00, 'gateway', 'DEMO-20', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-27 21:06:00', NULL, '2026-08-27 14:06:00', '2026-08-27 14:06:00'),
(21, 24, NULL, 'balance', 3780000.00, 'bank_transfer', 'DEMO-21', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-26 21:06:00', NULL, '2026-08-26 14:06:00', '2026-08-26 14:06:00'),
(22, 25, NULL, 'balance', 1890000.00, 'gateway', 'DEMO-22', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-08-25 21:06:00', NULL, '2026-08-25 14:06:00', '2026-08-25 14:06:00'),
(23, 26, NULL, 'balance', 3780000.00, 'bank_transfer', 'DEMO-23', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-18 21:06:00', NULL, '2026-09-18 14:06:00', '2026-09-18 14:06:00'),
(24, 27, NULL, 'balance', 3780000.00, 'gateway', 'DEMO-24', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-22 21:06:00', NULL, '2026-09-22 14:06:00', '2026-09-22 14:06:00'),
(25, 28, NULL, 'balance', 3780000.00, 'bank_transfer', 'DEMO-25', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-21 21:06:00', NULL, '2026-09-21 14:06:00', '2026-09-21 14:06:00'),
(26, 29, NULL, 'balance', 3780000.00, 'gateway', 'DEMO-26', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-20 21:06:00', NULL, '2026-09-20 14:06:00', '2026-09-20 14:06:00'),
(27, 30, NULL, 'balance', 1890000.00, 'bank_transfer', 'DEMO-27', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-19 21:06:00', NULL, '2026-09-19 14:06:00', '2026-09-19 14:06:00'),
(28, 31, NULL, 'deposit', 5940000.00, 'bank_transfer', 'DEMO-28', 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 19:36:51', 1, '2026-09-28 12:36:51', '2026-09-28 12:36:51');



CREATE TABLE `booking_surcharges` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `schedule_incident_id` bigint UNSIGNED NOT NULL,
  `kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `who_bears` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `customer_consent_at` datetime DEFAULT NULL,
  `consent_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `booking_transfers` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `from_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `to_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `from_tour_id` bigint UNSIGNED DEFAULT NULL,
  `to_tour_id` bigint UNSIGNED DEFAULT NULL,
  `initiated_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `contact_log_id` bigint UNSIGNED DEFAULT NULL,
  `reason_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_difference` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `cancellation_policies` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `effective_from` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `cancellation_policies` (`id`, `name`, `description`, `effective_from`, `created_at`, `updated_at`) VALUES
(1, 'Chính sách hủy tiêu chuẩn', 'Áp dụng cho tour nội địa. Phí hủy tăng dần khi càng sát ngày khởi hành, vì chi phí đã cam kết với nhà cung cấp càng khó hủy: khách sạn chốt phòng khoảng 7 ngày trước, nhà xe 3 ngày, suất ăn 1 đến 2 ngày. Khách đặt cọc 50% khi đăng ký và thanh toán nốt trước ngày khởi hành 10 ngày; quá hạn đó mà chưa thanh toán thì đơn được hủy và khách mất tiền cọc.', '2026-09-27 19:36:46', '2026-09-28 12:36:46', '2026-09-28 12:36:46');



CREATE TABLE `cancellation_policy_rules` (
  `id` bigint UNSIGNED NOT NULL,
  `cancellation_policy_id` bigint UNSIGNED NOT NULL,
  `min_days_before` smallint UNSIGNED NOT NULL DEFAULT '0',
  `max_days_before` smallint UNSIGNED DEFAULT NULL,
  `refund_percent` tinyint UNSIGNED NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `cancellation_policy_rules` (`id`, `cancellation_policy_id`, `min_days_before`, `max_days_before`, `refund_percent`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, 20, NULL, 100, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(2, 1, 15, 20, 75, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(3, 1, 12, 15, 50, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(4, 1, 8, 12, 50, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(5, 1, 2, 8, 10, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(6, 1, 0, 2, 0, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46');



CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Biển đảo', 'bien-dao', NULL, 1, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(2, 'Nghỉ dưỡng', 'nghi-duong', NULL, 1, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(3, 'Khám phá', 'kham-pha', NULL, 1, '2026-09-28 12:36:46', '2026-09-28 12:36:46');



CREATE TABLE `category_tour` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `category_tour` (`id`, `tour_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL),
(2, 1, 2, NULL, NULL),
(3, 2, 2, NULL, NULL),
(4, 2, 3, NULL, NULL),
(5, 3, 3, NULL, NULL),
(6, 4, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(7, 5, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(8, 6, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(9, 7, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(10, 7, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(11, 8, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(12, 8, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(13, 9, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(14, 9, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(15, 10, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(16, 10, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(17, 11, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(18, 12, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(19, 12, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(20, 13, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(21, 13, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(22, 14, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(23, 15, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00');



CREATE TABLE `checkpoint_photos` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `tour_itinerary_id` bigint UNSIGNED NOT NULL,
  `itinerary_checkpoint_id` bigint UNSIGNED DEFAULT NULL,
  `guide_id` bigint UNSIGNED DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `contact_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `handled_at` timestamp NULL DEFAULT NULL,
  `handled_by` bigint UNSIGNED DEFAULT NULL,
  `handling_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `customer_contact_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `channel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `outcome` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contacted_by` bigint UNSIGNED DEFAULT NULL,
  `contacted_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `discount_codes` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percent','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(12,2) NOT NULL,
  `minimum_order_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(12,2) DEFAULT NULL,
  `usage_limit` int UNSIGNED DEFAULT NULL,
  `per_customer_limit` int UNSIGNED DEFAULT NULL,
  `used_count` int UNSIGNED NOT NULL DEFAULT '0',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `discount_codes` (`id`, `code`, `name`, `type`, `value`, `minimum_order_amount`, `max_discount_amount`, `usage_limit`, `per_customer_limit`, `used_count`, `starts_at`, `expires_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'WELCOME15', 'Ưu đãi lần đặt tour đầu tiên', 'percent', 15.00, 1000000.00, 1000000.00, 100, NULL, 0, '2026-09-27 12:36:50', '2026-12-28 12:36:50', 1, '2026-09-28 12:36:50', '2026-09-28 12:36:50');



CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `group_booking_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `public_token` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_guests` int UNSIGNED NOT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_quote',
  `quoted_price_per_person` decimal(12,2) DEFAULT NULL,
  `quoted_free_slots` int UNSIGNED NOT NULL DEFAULT '0',
  `quote_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_expires_at` datetime DEFAULT NULL,
  `quoted_at` datetime DEFAULT NULL,
  `quoted_by` bigint UNSIGNED DEFAULT NULL,
  `rejected_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decided_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `group_booking_requests` (`id`, `public_token`, `tour_id`, `tour_schedule_id`, `customer_id`, `contact_name`, `contact_email`, `contact_phone`, `estimated_guests`, `company_name`, `tax_code`, `invoice_address`, `note`, `status`, `quoted_price_per_person`, `quoted_free_slots`, `quote_note`, `quote_expires_at`, `quoted_at`, `quoted_by`, `rejected_reason`, `decided_at`, `decided_by`, `created_at`, `updated_at`) VALUES
(1, 'e39b7ade-5fce-5f33-bdf0-457fbd1df169', 2, 10, NULL, 'Khách đoàn Demo 1', 'group_booking_requests-1@example.test', '0900000001', 28, 'Thông tin demo 1', '0000000000', 'Thông tin demo 1', 'Dữ liệu minh họa bảo vệ đồ án.', 'pending_quote', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(2, 'ff29c97a-4460-5a9f-b26d-0a5a6ffc996c', 2, 10, NULL, 'Khách đoàn Demo 2', 'group_booking_requests-2@example.test', '0900000002', 15, 'Thông tin demo 2', '0000000000', NULL, NULL, 'quoted', 1750000.00, 1, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-10-05 19:36:50', '2026-09-28 19:36:50', 1, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(3, 'a239fabc-8582-577c-800c-217cfd93af36', 2, 10, NULL, 'Khách đoàn Demo 3', 'group_booking_requests-3@example.test', '0900000003', 12, 'Thông tin demo 3', '0000000000', NULL, NULL, 'confirmed', 1800000.00, 1, NULL, '2026-10-05 19:36:50', '2026-09-28 19:36:50', 1, NULL, '2026-09-28 19:36:50', 1, '2026-09-28 12:36:50', '2026-09-28 12:36:50');



CREATE TABLE `guide_assignment_declines` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `guide_id` bigint UNSIGNED NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `declined_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `guide_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `guide_categories` (`id`, `user_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(2, 2, 2, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(3, 4, 1, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(4, 5, 3, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(5, 6, 3, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(6, 6, 2, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(7, 8, 3, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(8, 8, 2, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(9, 9, 3, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(10, 10, 1, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(11, 10, 2, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(12, 11, 1, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(13, 12, 1, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(14, 12, 3, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(15, 12, 2, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(16, 13, 2, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(17, 14, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(18, 14, 2, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(19, 15, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49');



CREATE TABLE `guide_handovers` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `from_guide_id` bigint UNSIGNED NOT NULL,
  `to_guide_id` bigint UNSIGNED NOT NULL,
  `handed_over_at` datetime NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `handover_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_emergency_cover` tinyint(1) NOT NULL DEFAULT '0',
  `acknowledged_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `guide_handover_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `requested_by` bigint UNSIGNED NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_state` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `guide_handover_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `guide_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `languages` json DEFAULT NULL,
  `regions` json DEFAULT NULL,
  `max_group_size` int UNSIGNED DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `guide_profiles` (`id`, `user_id`, `languages`, `regions`, `max_group_size`, `note`, `created_at`, `updated_at`) VALUES
(1, 2, '["Tiếng Việt", "Tiếng Anh"]', '["Hạ Long", "Ninh Bình", "Cát Bà"]', 35, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(2, 4, '["Tiếng Việt", "Tiếng Anh", "Tiếng Trung"]', '["Phú Quốc", "Nha Trang", "Đà Nẵng"]', 40, NULL, '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(3, 5, '["Tiếng Việt"]', '["Sapa", "Hà Giang", "Mộc Châu"]', 20, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:46', '2026-09-28 12:36:46'),
(4, 6, '["Tiếng Việt", "Tiếng Hàn"]', '["Đà Nẵng", "Hội An", "Huế"]', 25, NULL, '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(5, 8, '["Tiếng Việt", "Tiếng Anh"]', '["Cần Thơ", "Châu Đốc", "Cà Mau"]', 30, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(6, 9, '["Tiếng Việt", "Tiếng Anh", "Tiếng Nhật"]', '["Buôn Ma Thuột", "Pleiku", "Kon Tum"]', 22, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:47', '2026-09-28 12:36:47'),
(7, 10, '["Tiếng Việt", "Tiếng Hàn", "Tiếng Anh"]', '["Quy Nhơn", "Phú Yên", "Nha Trang"]', 28, NULL, '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(8, 11, '["Tiếng Việt"]', '["Côn Đảo", "Vũng Tàu", "Phú Quốc"]', 18, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(9, 12, '["Tiếng Việt", "Tiếng Anh", "Tiếng Pháp"]', '["Hà Nội", "Ninh Bình", "Hạ Long", "Sapa"]', 50, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(10, 13, '["Tiếng Việt", "Tiếng Trung"]', '["Huế", "Quảng Bình", "Hội An"]', 12, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:48', '2026-09-28 12:36:48'),
(11, 14, '["Tiếng Việt", "Tiếng Nga"]', '["Nha Trang", "Đà Lạt", "Mũi Né"]', 35, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(12, 15, '["Tiếng Việt"]', '["Hạ Long"]', 20, 'Dữ liệu minh họa bảo vệ đồ án.', '2026-09-28 12:36:49', '2026-09-28 12:36:49');



CREATE TABLE `incident_photos` (
  `id` bigint UNSIGNED NOT NULL,
  `schedule_incident_id` bigint UNSIGNED NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `itinerary_checkpoints` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_itinerary_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `sequence` int UNSIGNED NOT NULL DEFAULT '1',
  `is_required_photo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `itinerary_checkpoints` (`id`, `tour_itinerary_id`, `name`, `description`, `latitude`, `longitude`, `sequence`, `is_required_photo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Điểm tập trung khởi hành', 'Hướng dẫn viên đón đoàn, điểm danh trước khi xuất phát.', 20.9101000, 107.1839000, 1, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(2, 2, 'Điểm tập trung ngày 2', NULL, 20.9101000, 107.1839000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(3, 2, 'Điểm tham quan chính ngày 2', NULL, 20.9101000, 107.1839000, 2, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(4, 3, 'Điểm tập trung ngày 3', NULL, 20.9101000, 107.1839000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(5, 4, 'Điểm tập trung khởi hành', 'Hướng dẫn viên đón đoàn, điểm danh trước khi xuất phát.', 16.0544000, 108.2022000, 1, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(6, 5, 'Điểm tập trung ngày 2', NULL, 16.0544000, 108.2022000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(7, 5, 'Điểm tham quan chính ngày 2', NULL, 16.0544000, 108.2022000, 2, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(8, 6, 'Điểm tập trung ngày 3', NULL, 16.0544000, 108.2022000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(9, 6, 'Điểm tham quan chính ngày 3', NULL, 16.0544000, 108.2022000, 2, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(10, 7, 'Điểm tập trung ngày 4', NULL, 16.0544000, 108.2022000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(11, 8, 'Điểm tập trung khởi hành', 'Hướng dẫn viên đón đoàn, điểm danh trước khi xuất phát.', 22.3364000, 103.8438000, 1, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(12, 9, 'Điểm tập trung ngày 2', NULL, 22.3364000, 103.8438000, 1, 0, '2026-09-28 12:36:49', '2026-09-28 12:36:49');



CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_05_15_160007_create_categories_table', 1),
(5, '2026_05_15_161609_create_tours_table', 1),
(6, '2026_05_15_173527_create_tour_images_table', 1),
(7, '2026_05_15_173638_create_tour_itineraries_table', 1),
(8, '2026_05_15_173816_create_tour_schedules_table', 1),
(9, '2026_05_15_174210_create_services_table', 1),
(10, '2026_05_15_174251_create_tour_service_table', 1),
(11, '2026_05_15_175434_create_category_tour_table', 1),
(12, '2026_05_19_123600_create_personal_access_tokens_table', 1),
(13, '2026_06_17_161408_seed_admin_user', 1),
(14, '2026_06_17_163237_seed_guide_user', 1),
(15, '2026_06_28_000000_create_bookings_table', 1),
(16, '2026_06_28_000001_create_payment_logs_table', 1),
(17, '2026_07_02_132427_add_guide_id_to_tours_table', 1),
(18, '2026_07_02_132429_add_deleted_at_to_users_table', 1),
(19, '2026_07_05_000000_update_tour_statuses_to_active_inactive_full', 1),
(20, '2026_07_12_000000_move_guide_assignment_to_tour_schedules', 1),
(21, '2026_07_26_000000_add_route_fields_to_tour_itineraries_table', 1),
(22, '2026_07_26_144516_add_vehicle_info_to_tours_table', 1),
(23, '2026_07_26_144517_add_cancel_reason_to_bookings_table', 1),
(24, '2026_08_01_000000_create_discount_codes_table', 1),
(25, '2026_08_01_000001_add_discount_fields_to_bookings_table', 1),
(26, '2026_08_02_000000_add_guest_type_prices_to_tours_table', 1),
(27, '2026_08_02_000001_add_guest_type_counts_to_bookings_table', 1),
(28, '2026_08_03_000000_add_public_token_to_bookings_table', 1),
(29, '2026_08_06_000000_change_schedule_and_booking_dates_to_datetime', 1),
(30, '2026_08_06_024138_create_reviews_table', 1),
(31, '2026_08_06_024346_add_extra_fields_to_services_table', 1),
(32, '2026_08_06_120000_add_expires_at_to_bookings_table', 1),
(33, '2026_08_06_150000_create_attendance_tables', 1),
(34, '2026_08_06_170000_add_pickup_location_to_tours_table', 1),
(35, '2026_08_07_090000_create_booking_passengers_table', 1),
(36, '2026_08_07_100000_create_newsletter_subscribers_table', 1),
(37, '2026_08_11_000001_add_lifecycle_columns_to_tour_schedules', 1),
(38, '2026_08_11_000002_backfill_schedule_lifecycle_defaults', 1),
(39, '2026_08_11_000003_normalize_schedule_status_column', 1),
(40, '2026_08_12_000001_add_cancellation_columns_to_bookings_table', 1),
(41, '2026_08_12_000002_create_cancellation_policies_tables', 1),
(42, '2026_08_12_125954_create_itinerary_checkpoints_table', 1),
(43, '2026_08_12_130317_create_passenger_checkins_table', 1),
(44, '2026_08_12_130328_create_passenger_checkin_histories_table', 1),
(45, '2026_08_12_131737_add_checkpoint_fields_to_checkpoint_photos_table', 1),
(46, '2026_08_12_140000_add_schedule_and_late_entry_to_passenger_checkins', 1),
(47, '2026_08_12_150000_migrate_legacy_booking_checkins', 1),
(48, '2026_08_12_164034_create_notifications_table', 1),
(49, '2026_08_13_000001_add_reopen_fields_to_bookings_table', 1),
(50, '2026_08_13_000002_expand_booking_status_column', 1),
(51, '2026_08_13_000003_create_booking_change_requests_table', 1),
(52, '2026_08_14_000001_add_profile_fields_to_booking_passengers', 1),
(53, '2026_08_15_000001_create_booking_audit_logs_table', 1),
(54, '2026_08_15_000002_create_booking_transfers_table', 1),
(55, '2026_08_16_000001_add_type_to_tours_table', 1),
(56, '2026_08_17_000001_create_schedule_audit_logs_table', 1),
(57, '2026_08_17_000002_allow_many_guides_per_schedule', 1),
(58, '2026_08_17_000003_create_incident_tables', 1),
(59, '2026_08_17_000004_create_guide_handovers_table', 1),
(60, '2026_08_17_000005_create_guide_handover_requests_table', 1),
(61, '2026_08_17_000006_add_emergency_cover_to_guide_handovers', 1),
(62, '2026_08_17_000007_add_acknowledged_at_to_guide_handovers', 1),
(63, '2026_08_17_000008_create_guide_assignment_response_tables', 1),
(64, '2026_08_17_000009_create_guide_profile_tables', 1),
(65, '2026_08_17_000010_create_group_booking_tables', 1),
(66, '2026_08_17_000011_soft_delete_tours_and_stop_cascades', 1),
(67, '2026_08_23_000001_move_cost_bearer_to_each_surcharge', 1),
(68, '2026_08_23_000002_create_booking_contracts_table', 1),
(69, '2026_08_29_000001_cancellation_tiers_in_days_and_effective_from', 1),
(70, '2026_08_29_000002_create_customer_contact_logs_table', 1),
(71, '2026_08_30_000001_drop_dead_price_and_reopen_columns', 1),
(72, '2026_08_30_000002_add_moderation_and_reply_to_reviews', 1),
(73, '2026_08_30_000003_add_deposit_and_refund_details', 1),
(74, '2026_08_30_000004_add_departure_reminder_sent_at_to_bookings', 1),
(75, '2026_08_30_000005_create_contact_messages_table', 1),
(76, '2026_08_30_000006_drop_deposit_columns', 1),
(77, '2026_09_01_000001_keep_schedule_audit_logs_after_schedule_delete', 1),
(78, '2026_09_02_000001_add_understaffed_alert_to_tour_schedules', 1),
(79, '2026_09_02_000002_separate_seats_from_guests_on_bookings', 1),
(80, '2026_09_02_000003_snapshot_unit_prices_on_bookings', 1),
(81, '2026_09_02_000004_add_per_customer_limit_to_discount_codes', 1),
(82, '2026_09_03_000001_add_balance_reminder_marks_to_bookings', 1),
(83, '2026_09_03_000001_add_terms_accepted_at_to_bookings', 1),
(84, '2026_09_03_000002_add_leg_times_to_tour_schedules', 1),
(85, '2026_09_04_000001_add_sandbox_flag_to_tours', 1),
(86, '2026_09_04_000002_create_ai_knowledge_chunks_table', 1),
(87, '2026_09_04_000003_create_ai_chat_messages_table', 1),
(88, '2026_09_13_213654_create_booking_change_proposals_table', 1),
(89, '2026_09_15_163223_add_proposed_date_to_booking_change_proposals_table', 1),
(90, '2026_09_20_000001_remove_sandbox_tours', 1),
(91, '2026_09_20_000002_add_demo_clock_to_tour_schedules', 1),
(92, '2026_09_21_000001_remove_closed_schedule_status', 1),
(93, '2026_09_26_000001_add_schedule_targets_to_booking_proposals', 1),
(94, '2026_09_28_000001_add_images_to_tour_itineraries', 1);



CREATE TABLE `newsletter_subscribers` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint UNSIGNED NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `passenger_checkins` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_passenger_id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `itinerary_checkpoint_id` bigint UNSIGNED NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `checked_by` bigint UNSIGNED DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `is_late_entry` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `passenger_checkin_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `passenger_checkin_id` bigint UNSIGNED NOT NULL,
  `old_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `payment_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED DEFAULT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vnpay',
  `transaction_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `is_valid_signature` tinyint(1) NOT NULL DEFAULT '0',
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` bigint UNSIGNED DEFAULT NULL,
  `moderation_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `replied_at` timestamp NULL DEFAULT NULL,
  `replied_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `reviews` (`id`, `tour_id`, `user_id`, `rating`, `comment`, `status`, `moderated_at`, `moderated_by`, `moderation_note`, `reply`, `replied_at`, `replied_by`, `created_at`, `updated_at`) VALUES
(1, 1, 17, 5, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(2, 1, 18, 5, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(3, 1, 19, 4, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(4, 2, 17, 5, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(5, 2, 18, 4, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(6, 3, 17, 5, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50'),
(7, 3, 18, 4, 'Chuyến đi thú vị, hướng dẫn viên nhiệt tình.', 'approved', '2026-09-28 12:36:50', NULL, NULL, NULL, NULL, NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50');



CREATE TABLE `schedule_audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `actor_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `schedule_incidents` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `tour_itinerary_id` bigint UNSIGNED DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reported',
  `occurred_at` datetime NOT NULL,
  `reported_late` tinyint(1) NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_by` bigint UNSIGNED NOT NULL,
  `resolution` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_delta` decimal(12,2) DEFAULT NULL,
  `who_bears` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `services` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `services` (`id`, `name`, `icon`, `description`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Xe đưa đón', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(2, 'Khách sạn 4 sao', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(3, 'Ăn sáng', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(4, 'Hướng dẫn viên', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(5, 'Vé tham quan', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(6, 'Bảo hiểm du lịch', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(7, 'Vé máy bay', NULL, NULL, NULL, 1, '2026-09-28 12:36:49', '2026-09-28 12:36:49');



CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tours` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `adult_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `child_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `infant_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `thumbnail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_of_days` int NOT NULL DEFAULT '1',
  `number_of_nights` int NOT NULL DEFAULT '0',
  `start_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive','full') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shared',
  `cancellation_policy_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tours` (`id`, `admin_id`, `title`, `slug`, `description`, `adult_price`, `child_price`, `infant_price`, `thumbnail`, `number_of_days`, `number_of_nights`, `start_location`, `end_location`, `vehicle_info`, `pickup_location`, `is_featured`, `status`, `type`, `cancellation_policy_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Tour Hạ Long 3N2Đ', 'tour-ha-long-3n2d', 'Khám phá vịnh Hạ Long, nghỉ dưỡng và trải nghiệm hải trình ngắn ngày phù hợp gia đình.', 3190000.00, 2233000.00, 0.00, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e', 3, 2, 'Hà Nội', 'Hạ Long', 'Xe giường nằm 34 chỗ đời mới, có wifi và nước uống', 'Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền, Hoàn Kiếm (có mặt trước giờ khởi hành 30 phút)', 1, 'active', 'shared', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(2, 1, 'Tour Đà Nẵng - Hội An 4N3Đ', 'tour-da-nang-hoi-an-4n3d', 'Combo biển, phố cổ và ẩm thực miền Trung với lịch trình cân bằng giữa nghỉ dưỡng và khám phá.', 3890000.00, 2723000.00, 0.00, 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86', 4, 3, 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Vé máy bay khứ hồi + xe du lịch 29 chỗ tại điểm đến', 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 9 (tập trung trước giờ bay 2 tiếng)', 0, 'active', 'shared', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(3, 1, 'Tour Sapa - Fansipan 2N1Đ', 'tour-sapa-fansipan-2n1d', 'Săn mây Fansipan, dạo bản Cát Cát và thưởng thức ẩm thực Tây Bắc trong hai ngày cuối tuần.', 1890000.00, 1323000.00, 0.00, 'https://images.unsplash.com/photo-1570366583862-f91883984fde', 2, 1, 'Hà Nội', 'Sapa', 'Xe giường nằm cao cấp 22 chỗ, khởi hành đêm', 'Bến xe Mỹ Đình - Cổng chính, Nam Từ Liêm (có mặt trước giờ khởi hành 30 phút)', 1, 'active', 'shared', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(4, 1, 'Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An – Tuyệt Tịnh Cốc', 'ha-noi-ha-long-bai-dinh-trang-an-4n3d', 'Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An – Tuyệt Tịnh Cốc. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 9390000.00, 6573000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4681_trang-an-3.webp', 4, 3, 'TP. Hồ Chí Minh', 'Hà Nội', 'Máy bay, xe du lịch', 'Sân bay Tân Sơn Nhất', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(5, 1, 'Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính', 'sapa-fansipan-ha-noi-yen-tu-ha-long-ninh-binh-6n5d', 'Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 12590000.00, 8813000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_3966_view-of-sapa-town.webp', 6, 5, 'TP. Hồ Chí Minh', 'Ninh Bình', 'Máy bay, xe du lịch', 'Sân bay Tân Sơn Nhất', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(6, 1, 'Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế KDL Bà Nà – Cầu Vàng – Làng Hương Thủy Xuân', 'da-nang-ba-na-hoi-an-phong-nha-hue-4n3d', 'Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế KDL Bà Nà – Cầu Vàng – Làng Hương Thủy Xuân. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 7990000.00, 5593000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_9810_thap-tram-huong.webp', 4, 3, 'TP. Hồ Chí Minh', 'Huế', 'Máy bay, xe du lịch', 'Sân bay Tân Sơn Nhất', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(7, 1, 'Nha Trang – Biển Nhũ Tiên – Chùa Long Sơn – Làng gốm Bàu Trúc – Làng yến Mai Sinh', 'nha-trang-nhu-tien-chua-long-son-bau-truc-3n2d', 'Nha Trang – Biển Nhũ Tiên – Chùa Long Sơn – Làng gốm Bàu Trúc – Làng yến Mai Sinh. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 2390000.00, 1673000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_11802_ganh-da-dia.webp', 3, 2, 'TP. Hồ Chí Minh', 'Nha Trang', 'Xe du lịch', 'TP. Hồ Chí Minh', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(8, 1, 'Khánh Hòa – Marina Beach Club – Nha Trang Xưa – Biển Nhũ Tiên – I-Resort – VinWonders', 'khanh-hoa-marina-nha-trang-4n3d', 'Khánh Hòa – Marina Beach Club – Nha Trang Xưa – Biển Nhũ Tiên – I-Resort – VinWonders. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 3590000.00, 2513000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_9386_bai-bien-nha-trang-2.webp', 4, 3, 'TP. Hồ Chí Minh', 'Nha Trang', 'Xe du lịch', 'TP. Hồ Chí Minh', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(9, 1, 'Phú Quốc: Bãi Sao – Hòn Thơm – VinWonders – Safari – Thị Trấn Hoàng Hôn – Grand World', 'phu-quoc-bai-sao-hon-thom-vinwonders-4n3d', 'Phú Quốc: Bãi Sao – Hòn Thơm – VinWonders – Safari – Thị Trấn Hoàng Hôn – Grand World. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 9590000.00, 6713000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13564_vinwonders.webp', 4, 3, 'TP. Hồ Chí Minh', 'Phú Quốc', 'Máy bay, xe du lịch', 'Sân bay Tân Sơn Nhất', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(10, 1, 'Ngắm Hoàng Hôn Tại Sunset Sanato – Thử Tài Câu Cá – VinWonders & Safari', 'phu-quoc-sunset-sanato-vinwonders-safari-3n2d', 'Ngắm Hoàng Hôn Tại Sunset Sanato – Thử Tài Câu Cá – VinWonders & Safari. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 3990000.00, 2793000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13063_trai-nghiem-tour-song-ba-lai-minh-hoa-6.webp', 3, 2, 'Cần Thơ', 'Phú Quốc', 'Xe du lịch, tàu cao tốc', 'Cần Thơ', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(11, 1, 'Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', 'mien-tay-vinh-can-tho-ca-mau-dat-mui-5n4d', 'Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 8990000.00, 6293000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13010_con-son-vuon-trai-cay.webp', 5, 4, 'Vinh', 'Sóc Trăng', 'Máy bay, xe du lịch', 'Sân bay Vinh', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(12, 1, 'Hành trình Hoa và Biển: Đà Lạt – Nha Trang', 'da-lat-nha-trang-hoa-va-bien-5n4d', 'Hành trình Hoa và Biển: Đà Lạt – Nha Trang. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 4990000.00, 3493000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_7324_quang-truong-lam-vien-1.webp', 5, 4, 'TP. Hồ Chí Minh', 'Nha Trang', 'Xe du lịch', 'TP. Hồ Chí Minh', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(13, 1, 'Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar', 'nha-trang-phu-yen-5n4d', 'Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 4990000.00, 3493000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_8392_mu-cang-chai-yen-bai.webp', 5, 4, 'TP. Hồ Chí Minh', 'Nha Trang', 'Xe du lịch', 'TP. Hồ Chí Minh', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(14, 1, 'Đà Nẵng – Phố Cổ Hội An – Bà Nà – Cầu Vàng – Vườn Tượng Apec – Cầu Tình Yêu', 'da-nang-hoi-an-ba-na-cau-vang-3n2d', 'Đà Nẵng – Phố Cổ Hội An – Bà Nà – Cầu Vàng – Vườn Tượng Apec – Cầu Tình Yêu. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 5290000.00, 3703000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_6777_cat-cat.webp', 3, 2, 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Máy bay, xe du lịch', 'Sân bay Tân Sơn Nhất', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL),
(15, 1, 'Hà Nội – Ninh Bình – Hạ Long – Yên Tử – Tràng An', 'ha-noi-ninh-binh-ha-long-yen-tu-4n3d', 'Hà Nội – Ninh Bình – Hạ Long – Yên Tử – Tràng An. Chương trình tham khảo theo tour nội địa đang được Vietravel giới thiệu trên travel.com.vn.', 8990000.00, 6293000.00, 0.00, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_10189_vinwonders.webp', 4, 3, 'Hà Nội', 'Hạ Long', 'Xe du lịch', 'Hà Nội', 0, 'active', 'shared', 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL);



CREATE TABLE `tour_images` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tour_images` (`id`, `tour_id`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e', '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(2, 1, 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66', '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(3, 2, 'https://images.unsplash.com/photo-1512813195386-6cf811ad3542', '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(4, 2, 'https://images.unsplash.com/photo-1543877087-ebf71fde2be1', '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(5, 3, 'https://images.unsplash.com/photo-1570366583862-f91883984fde', '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(13, 4, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4681_trang-an-3.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(14, 5, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_3966_view-of-sapa-town.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(15, 6, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_9810_thap-tram-huong.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(16, 7, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_11802_ganh-da-dia.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(17, 8, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_9386_bai-bien-nha-trang-2.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(18, 9, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13564_vinwonders.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(19, 10, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13063_trai-nghiem-tour-song-ba-lai-minh-hoa-6.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(20, 11, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13010_con-son-vuon-trai-cay.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(21, 12, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_7324_quang-truong-lam-vien-1.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(22, 13, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_8392_mu-cang-chai-yen-bai.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(23, 14, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_6777_cat-cat.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(24, 15, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_10189_vinwonders.webp', '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(25, 7, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(26, 7, 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(27, 8, 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(28, 8, 'https://images.unsplash.com/photo-1512813195386-6cf811ad3542', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(29, 9, 'https://images.unsplash.com/photo-1512813195386-6cf811ad3542', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(30, 9, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4967_ba-na-hill-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(31, 10, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4967_ba-na-hill-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(32, 10, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_3318_cau-rong-hoang-hon.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(33, 11, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_11106_ha-long-bay.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(34, 11, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4967_ba-na-hill-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(35, 12, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4967_ba-na-hill-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(36, 12, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_3318_cau-rong-hoang-hon.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(37, 13, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_3318_cau-rong-hoang-hon.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(38, 13, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_10980_dji0785.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(39, 14, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_10980_dji0785.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(40, 14, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_10268_sunset-sanato-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(41, 15, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_10268_sunset-sanato-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10'),
(42, 15, 'https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_3464_dao-khi-2.webp', '2026-09-28 13:30:10', '2026-09-28 13:30:10');



CREATE TABLE `tour_itineraries` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `day_number` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_point` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_point` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_points` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rest_stops` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `images` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tour_itineraries` (`id`, `tour_id`, `day_number`, `title`, `start_point`, `end_point`, `route_points`, `rest_stops`, `content`, `created_at`, `updated_at`, `images`) VALUES
(1, 4, 1, 'Ngày 1: TP. Hồ Chí Minh – Hà Nội', 'TP. Hồ Chí Minh', 'Hà Nội', 'TP. Hồ Chí Minh – Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An – Tuyệt Tịnh Cốc. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Hà Nội.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(2, 4, 2, 'Ngày 2: Hà Nội – Vịnh Hạ Long', 'Hà Nội', 'Vịnh Hạ Long', 'TP. Hồ Chí Minh – Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An – Tuyệt Tịnh Cốc. Ngày 2: di chuyển/tham quan tuyến Hà Nội – Vịnh Hạ Long.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(3, 4, 3, 'Ngày 3: Vịnh Hạ Long – Chùa Bái Đính', 'Vịnh Hạ Long', 'Chùa Bái Đính', 'TP. Hồ Chí Minh – Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Vịnh Hạ Long – Chùa Bái Đính – Tràng An – Tuyệt Tịnh Cốc. Ngày 3: di chuyển/tham quan tuyến Vịnh Hạ Long – Chùa Bái Đính.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(4, 5, 1, 'Ngày 1: TP. Hồ Chí Minh – Sapa', 'TP. Hồ Chí Minh', 'Sapa', 'TP. Hồ Chí Minh – Sapa – Fansipan – Hà Nội – Ninh Bình', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Sapa.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(5, 5, 2, 'Ngày 2: Sapa – Fansipan', 'Sapa', 'Fansipan', 'TP. Hồ Chí Minh – Sapa – Fansipan – Hà Nội – Ninh Bình', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Ngày 2: di chuyển/tham quan tuyến Sapa – Fansipan.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(6, 5, 3, 'Ngày 3: Fansipan – Hà Nội', 'Fansipan', 'Hà Nội', 'TP. Hồ Chí Minh – Sapa – Fansipan – Hà Nội – Ninh Bình', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Ngày 3: di chuyển/tham quan tuyến Fansipan – Hà Nội.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(7, 5, 4, 'Ngày 4: Hà Nội – Ninh Bình', 'Hà Nội', 'Ninh Bình', 'TP. Hồ Chí Minh – Sapa – Fansipan – Hà Nội – Ninh Bình', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Ngày 4: di chuyển/tham quan tuyến Hà Nội – Ninh Bình.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(8, 5, 5, 'Ngày 5: Khám phá Ninh Bình', 'Ninh Bình', 'Ninh Bình', 'TP. Hồ Chí Minh – Sapa – Fansipan – Hà Nội – Ninh Bình', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Sapa – Fansipan – Hà Nội – Yên Tử – Hạ Long – Ninh Bình – Tràng An – Bái Đính. Ngày 5: di chuyển/tham quan tuyến Ninh Bình – Ninh Bình.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(9, 6, 1, 'Ngày 1: TP. Hồ Chí Minh – Sapa', 'TP. Hồ Chí Minh', 'Sapa', 'TP. Hồ Chí Minh – Sapa – Fansipan – Yên Tử – Hạ Long – Tràng An – Bái Đính', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế KDL Bà Nà – Cầu Vàng – Làng Hương Thủy Xuân. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Sapa.', '2026-09-28 12:36:49', '2026-09-28 09:00:00', '[]'),
(10, 6, 2, 'Ngày 2: Sapa – Fansipan', 'Sapa', 'Fansipan', 'TP. Hồ Chí Minh – Sapa – Fansipan – Yên Tử – Hạ Long – Tràng An – Bái Đính', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế KDL Bà Nà – Cầu Vàng – Làng Hương Thủy Xuân. Ngày 2: di chuyển/tham quan tuyến Sapa – Fansipan.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(11, 6, 3, 'Ngày 3: Fansipan – Yên Tử', 'Fansipan', 'Yên Tử', 'TP. Hồ Chí Minh – Sapa – Fansipan – Yên Tử – Hạ Long – Tràng An – Bái Đính', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế KDL Bà Nà – Cầu Vàng – Làng Hương Thủy Xuân. Ngày 3: di chuyển/tham quan tuyến Fansipan – Yên Tử.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(12, 7, 1, 'Ngày 1: TP. Hồ Chí Minh – Đà Nẵng', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'TP. Hồ Chí Minh – Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Biển Nhũ Tiên – Chùa Long Sơn – Làng gốm Bàu Trúc – Làng yến Mai Sinh. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Đà Nẵng.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(13, 7, 2, 'Ngày 2: Đà Nẵng – Bà Nà', 'Đà Nẵng', 'Bà Nà', 'TP. Hồ Chí Minh – Đà Nẵng – Bà Nà – Hội An – Phong Nha – Huế', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Biển Nhũ Tiên – Chùa Long Sơn – Làng gốm Bàu Trúc – Làng yến Mai Sinh. Ngày 2: di chuyển/tham quan tuyến Đà Nẵng – Bà Nà.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(14, 8, 1, 'Ngày 1: TP. Hồ Chí Minh – Nha Trang', 'TP. Hồ Chí Minh', 'Nha Trang', 'TP. Hồ Chí Minh – Nha Trang – Phú Yên – Gành Đá Dĩa – Dốc Lết', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Khánh Hòa – Marina Beach Club – Nha Trang Xưa – Biển Nhũ Tiên – I-Resort – VinWonders. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Nha Trang.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(15, 8, 2, 'Ngày 2: Nha Trang – Phú Yên', 'Nha Trang', 'Phú Yên', 'TP. Hồ Chí Minh – Nha Trang – Phú Yên – Gành Đá Dĩa – Dốc Lết', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Khánh Hòa – Marina Beach Club – Nha Trang Xưa – Biển Nhũ Tiên – I-Resort – VinWonders. Ngày 2: di chuyển/tham quan tuyến Nha Trang – Phú Yên.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(16, 8, 3, 'Ngày 3: Phú Yên – Gành Đá Dĩa', 'Phú Yên', 'Gành Đá Dĩa', 'TP. Hồ Chí Minh – Nha Trang – Phú Yên – Gành Đá Dĩa – Dốc Lết', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Khánh Hòa – Marina Beach Club – Nha Trang Xưa – Biển Nhũ Tiên – I-Resort – VinWonders. Ngày 3: di chuyển/tham quan tuyến Phú Yên – Gành Đá Dĩa.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(17, 9, 1, 'Ngày 1: TP. Hồ Chí Minh – Nha Trang', 'TP. Hồ Chí Minh', 'Nha Trang', 'TP. Hồ Chí Minh – Nha Trang – I-Resort – VinWonders', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Phú Quốc: Bãi Sao – Hòn Thơm – VinWonders – Safari – Thị Trấn Hoàng Hôn – Grand World. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Nha Trang.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(18, 9, 2, 'Ngày 2: Nha Trang – I-Resort', 'Nha Trang', 'I-Resort', 'TP. Hồ Chí Minh – Nha Trang – I-Resort – VinWonders', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Phú Quốc: Bãi Sao – Hòn Thơm – VinWonders – Safari – Thị Trấn Hoàng Hôn – Grand World. Ngày 2: di chuyển/tham quan tuyến Nha Trang – I-Resort.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(19, 9, 3, 'Ngày 3: I-Resort – VinWonders', 'I-Resort', 'VinWonders', 'TP. Hồ Chí Minh – Nha Trang – I-Resort – VinWonders', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Phú Quốc: Bãi Sao – Hòn Thơm – VinWonders – Safari – Thị Trấn Hoàng Hôn – Grand World. Ngày 3: di chuyển/tham quan tuyến I-Resort – VinWonders.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(20, 10, 1, 'Ngày 1: TP. Hồ Chí Minh – Nha Trang', 'TP. Hồ Chí Minh', 'Nha Trang', 'TP. Hồ Chí Minh – Nha Trang – Đà Lạt – Langbiang – God Valley', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Ngắm Hoàng Hôn Tại Sunset Sanato – Thử Tài Câu Cá – VinWonders & Safari. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Nha Trang.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(21, 10, 2, 'Ngày 2: Nha Trang – Đà Lạt', 'Nha Trang', 'Đà Lạt', 'TP. Hồ Chí Minh – Nha Trang – Đà Lạt – Langbiang – God Valley', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Ngắm Hoàng Hôn Tại Sunset Sanato – Thử Tài Câu Cá – VinWonders & Safari. Ngày 2: di chuyển/tham quan tuyến Nha Trang – Đà Lạt.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(22, 11, 1, 'Ngày 1: Vinh – Cần Thơ', 'Vinh', 'Cần Thơ', 'Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng. Ngày 1: di chuyển/tham quan tuyến Vinh – Cần Thơ.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(23, 11, 2, 'Ngày 2: Cần Thơ – Cà Mau', 'Cần Thơ', 'Cà Mau', 'Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng. Ngày 2: di chuyển/tham quan tuyến Cần Thơ – Cà Mau.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(24, 11, 3, 'Ngày 3: Cà Mau – Đất Mũi', 'Cà Mau', 'Đất Mũi', 'Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng. Ngày 3: di chuyển/tham quan tuyến Cà Mau – Đất Mũi.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(25, 11, 4, 'Ngày 4: Đất Mũi – Bạc Liêu', 'Đất Mũi', 'Bạc Liêu', 'Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Miền Tây: Vinh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng. Ngày 4: di chuyển/tham quan tuyến Đất Mũi – Bạc Liêu.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(26, 12, 1, 'Ngày 1: TP. Hồ Chí Minh – Cần Thơ', 'TP. Hồ Chí Minh', 'Cần Thơ', 'TP. Hồ Chí Minh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hành trình Hoa và Biển: Đà Lạt – Nha Trang. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Cần Thơ.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(27, 12, 2, 'Ngày 2: Cần Thơ – Cà Mau', 'Cần Thơ', 'Cà Mau', 'TP. Hồ Chí Minh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hành trình Hoa và Biển: Đà Lạt – Nha Trang. Ngày 2: di chuyển/tham quan tuyến Cần Thơ – Cà Mau.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(28, 12, 3, 'Ngày 3: Cà Mau – Đất Mũi', 'Cà Mau', 'Đất Mũi', 'TP. Hồ Chí Minh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hành trình Hoa và Biển: Đà Lạt – Nha Trang. Ngày 3: di chuyển/tham quan tuyến Cà Mau – Đất Mũi.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(29, 12, 4, 'Ngày 4: Đất Mũi – Bạc Liêu', 'Đất Mũi', 'Bạc Liêu', 'TP. Hồ Chí Minh – Cần Thơ – Cà Mau – Đất Mũi – Bạc Liêu – Sóc Trăng', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hành trình Hoa và Biển: Đà Lạt – Nha Trang. Ngày 4: di chuyển/tham quan tuyến Đất Mũi – Bạc Liêu.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(30, 13, 1, 'Ngày 1: TP. Hồ Chí Minh – Châu Đốc', 'TP. Hồ Chí Minh', 'Châu Đốc', 'TP. Hồ Chí Minh – Châu Đốc – Núi Cấm – Rừng Tràm Trà Sư – Cần Thơ', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Châu Đốc.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(31, 13, 2, 'Ngày 2: Châu Đốc – Núi Cấm', 'Châu Đốc', 'Núi Cấm', 'TP. Hồ Chí Minh – Châu Đốc – Núi Cấm – Rừng Tràm Trà Sư – Cần Thơ', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar. Ngày 2: di chuyển/tham quan tuyến Châu Đốc – Núi Cấm.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(32, 13, 3, 'Ngày 3: Núi Cấm – Rừng Tràm Trà Sư', 'Núi Cấm', 'Rừng Tràm Trà Sư', 'TP. Hồ Chí Minh – Châu Đốc – Núi Cấm – Rừng Tràm Trà Sư – Cần Thơ', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar. Ngày 3: di chuyển/tham quan tuyến Núi Cấm – Rừng Tràm Trà Sư.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(33, 13, 4, 'Ngày 4: Rừng Tràm Trà Sư – Cần Thơ', 'Rừng Tràm Trà Sư', 'Cần Thơ', 'TP. Hồ Chí Minh – Châu Đốc – Núi Cấm – Rừng Tràm Trà Sư – Cần Thơ', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Nha Trang – Phú Yên – Tháp Nghinh Phong – Gành Đá Dĩa – Dốc Lết – Tháp Bà Ponagar. Ngày 4: di chuyển/tham quan tuyến Rừng Tràm Trà Sư – Cần Thơ.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(34, 14, 1, 'Ngày 1: TP. Hồ Chí Minh – Mỹ Tho', 'TP. Hồ Chí Minh', 'Mỹ Tho', 'TP. Hồ Chí Minh – Mỹ Tho – Thới Sơn – Cồn Phụng – Bến Tre', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Đà Nẵng – Phố Cổ Hội An – Bà Nà – Cầu Vàng – Vườn Tượng Apec – Cầu Tình Yêu. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Mỹ Tho.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(35, 14, 2, 'Ngày 2: Mỹ Tho – Thới Sơn', 'Mỹ Tho', 'Thới Sơn', 'TP. Hồ Chí Minh – Mỹ Tho – Thới Sơn – Cồn Phụng – Bến Tre', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Đà Nẵng – Phố Cổ Hội An – Bà Nà – Cầu Vàng – Vườn Tượng Apec – Cầu Tình Yêu. Ngày 2: di chuyển/tham quan tuyến Mỹ Tho – Thới Sơn.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(36, 15, 1, 'Ngày 1: TP. Hồ Chí Minh – Phú Quốc', 'TP. Hồ Chí Minh', 'Phú Quốc', 'TP. Hồ Chí Minh – Phú Quốc – Hòn Thơm – VinWonders – Safari – Grand World', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Ninh Bình – Hạ Long – Yên Tử – Tràng An. Ngày 1: di chuyển/tham quan tuyến TP. Hồ Chí Minh – Phú Quốc.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(37, 15, 2, 'Ngày 2: Phú Quốc – Hòn Thơm', 'Phú Quốc', 'Hòn Thơm', 'TP. Hồ Chí Minh – Phú Quốc – Hòn Thơm – VinWonders – Safari – Grand World', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Ninh Bình – Hạ Long – Yên Tử – Tràng An. Ngày 2: di chuyển/tham quan tuyến Phú Quốc – Hòn Thơm.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]'),
(38, 15, 3, 'Ngày 3: Hòn Thơm – VinWonders', 'Hòn Thơm', 'VinWonders', 'TP. Hồ Chí Minh – Phú Quốc – Hòn Thơm – VinWonders – Safari – Grand World', NULL, 'Lịch trình tham khảo theo chương trình Vietravel cho tour Hà Nội – Ninh Bình – Hạ Long – Yên Tử – Tràng An. Ngày 3: di chuyển/tham quan tuyến Hòn Thơm – VinWonders.', '2026-09-28 09:00:00', '2026-09-28 09:00:00', '[]');



CREATE TABLE `tour_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `arrival_at` datetime DEFAULT NULL,
  `return_departure_at` datetime DEFAULT NULL,
  `max_people` int NOT NULL DEFAULT '10',
  `min_people` int UNSIGNED NOT NULL DEFAULT '1',
  `booking_deadline` datetime DEFAULT NULL,
  `understaffed_alert_sent_at` timestamp NULL DEFAULT NULL,
  `booked_people` int NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `merged_into_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  `demo_time` datetime DEFAULT NULL,
  `demo_time_set_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tour_schedules` (`id`, `tour_id`, `start_date`, `end_date`, `arrival_at`, `return_departure_at`, `max_people`, `min_people`, `booking_deadline`, `understaffed_alert_sent_at`, `booked_people`, `status`, `confirmed_at`, `cancelled_at`, `created_at`, `updated_at`, `cancelled_by`, `cancelled_reason`, `merged_into_schedule_id`, `is_private`, `demo_time`, `demo_time_set_at`) VALUES
(1, 1, '2026-07-15 05:30:00', '2026-07-17 18:00:00', '2026-07-15 11:30:00', '2026-07-17 13:00:00', 20, 8, '2026-07-12 05:30:00', NULL, 8, 'completed', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(2, 1, '2026-08-19 05:30:00', '2026-08-21 18:00:00', '2026-08-19 11:30:00', '2026-08-21 13:00:00', 20, 8, '2026-08-16 05:30:00', NULL, 12, 'completed', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(3, 1, '2026-10-05 05:30:00', '2026-10-07 18:00:00', '2026-10-05 11:30:00', '2026-10-07 13:00:00', 20, 8, '2026-10-02 05:30:00', NULL, 4, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(4, 1, '2026-10-12 05:30:00', '2026-10-14 18:00:00', '2026-10-12 11:30:00', '2026-10-14 13:00:00', 20, 8, '2026-10-09 05:30:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(5, 1, '2026-11-12 05:30:00', '2026-11-14 18:00:00', '2026-11-12 11:30:00', '2026-11-14 13:00:00', 20, 8, '2026-11-09 05:30:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(6, 1, '2026-12-17 05:30:00', '2026-12-19 18:00:00', '2026-12-17 11:30:00', '2026-12-19 13:00:00', 20, 8, '2026-12-14 05:30:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(7, 2, '2026-08-09 08:00:00', '2026-08-12 18:00:00', '2026-08-09 14:00:00', '2026-08-12 13:00:00', 25, 10, '2026-08-06 08:00:00', NULL, 10, 'completed', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(8, 2, '2026-09-30 08:00:00', '2026-10-03 18:00:00', '2026-09-30 14:00:00', '2026-10-03 13:00:00', 25, 10, '2026-09-27 08:00:00', NULL, 2, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(9, 2, '2026-10-08 08:00:00', '2026-10-11 18:00:00', '2026-10-08 14:00:00', '2026-10-11 13:00:00', 25, 10, '2026-10-05 08:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(10, 2, '2026-11-05 08:00:00', '2026-11-08 18:00:00', '2026-11-05 14:00:00', '2026-11-08 13:00:00', 25, 10, '2026-11-02 08:00:00', NULL, 12, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(11, 2, '2026-12-03 08:00:00', '2026-12-06 18:00:00', '2026-12-03 14:00:00', '2026-12-06 13:00:00', 25, 10, '2026-11-30 08:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(12, 3, '2026-09-06 21:00:00', '2026-09-07 18:00:00', '2026-09-07 03:00:00', '2026-09-07 13:00:00', 22, 9, '2026-09-03 21:00:00', NULL, 9, 'completed', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(13, 3, '2026-10-01 21:00:00', '2026-10-02 18:00:00', '2026-10-02 03:00:00', '2026-10-02 13:00:00', 22, 10, '2026-09-28 21:00:00', NULL, 9, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(14, 3, '2026-10-07 21:00:00', '2026-10-08 18:00:00', '2026-10-08 03:00:00', '2026-10-08 13:00:00', 22, 9, '2026-10-04 21:00:00', NULL, 1, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:50', NULL, NULL, NULL, 0, NULL, NULL),
(15, 3, '2026-10-19 21:00:00', '2026-10-20 18:00:00', '2026-10-20 03:00:00', '2026-10-20 13:00:00', 22, 9, '2026-10-16 21:00:00', NULL, 0, 'cancelled', NULL, '2026-09-26 19:36:49', '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, 'Dữ liệu minh họa bảo vệ đồ án.', NULL, 0, NULL, NULL),
(16, 3, '2026-10-28 21:00:00', '2026-10-29 18:00:00', '2026-10-29 03:00:00', '2026-10-29 13:00:00', 22, 9, '2026-10-25 21:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(17, 3, '2026-11-27 21:00:00', '2026-11-28 18:00:00', '2026-11-28 03:00:00', '2026-11-28 13:00:00', 22, 9, '2026-11-24 21:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL, NULL, NULL, 0, NULL, NULL),
(18, 7, '2026-10-02 06:00:00', '2026-10-04 18:00:00', '2026-10-02 12:00:00', '2026-10-04 13:00:00', 20, 10, '2026-09-29 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(19, 9, '2026-10-07 06:00:00', '2026-10-10 18:00:00', '2026-10-07 12:00:00', '2026-10-10 13:00:00', 25, 10, '2026-10-04 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(20, 12, '2026-10-07 06:00:00', '2026-10-11 18:00:00', '2026-10-07 12:00:00', '2026-10-11 13:00:00', 20, 10, '2026-10-04 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(21, 5, '2026-10-08 06:00:00', '2026-10-13 18:00:00', '2026-10-08 12:00:00', '2026-10-13 13:00:00', 25, 10, '2026-10-05 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(22, 8, '2026-10-09 06:00:00', '2026-10-12 18:00:00', '2026-10-09 12:00:00', '2026-10-12 13:00:00', 20, 10, '2026-10-06 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(23, 6, '2026-10-12 06:00:00', '2026-10-15 18:00:00', '2026-10-12 12:00:00', '2026-10-15 13:00:00', 20, 10, '2026-10-09 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(24, 11, '2026-10-14 06:00:00', '2026-10-18 18:00:00', '2026-10-14 12:00:00', '2026-10-18 13:00:00', 20, 10, '2026-10-11 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(25, 4, '2026-10-15 06:00:00', '2026-10-18 18:00:00', '2026-10-15 12:00:00', '2026-10-18 13:00:00', 20, 10, '2026-10-12 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(26, 7, '2026-10-16 06:00:00', '2026-10-18 18:00:00', '2026-10-16 12:00:00', '2026-10-18 13:00:00', 20, 10, '2026-10-13 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(27, 10, '2026-10-17 06:00:00', '2026-10-19 18:00:00', '2026-10-17 12:00:00', '2026-10-19 13:00:00', 20, 10, '2026-10-14 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(28, 15, '2026-10-20 06:00:00', '2026-10-23 18:00:00', '2026-10-20 12:00:00', '2026-10-23 13:00:00', 20, 10, '2026-10-17 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(29, 14, '2026-10-23 06:00:00', '2026-10-25 18:00:00', '2026-10-23 12:00:00', '2026-10-25 13:00:00', 20, 10, '2026-10-20 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(30, 13, '2026-10-24 06:00:00', '2026-10-28 18:00:00', '2026-10-24 12:00:00', '2026-10-28 13:00:00', 20, 10, '2026-10-21 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(31, 9, '2026-10-28 06:00:00', '2026-10-31 18:00:00', '2026-10-28 12:00:00', '2026-10-31 13:00:00', 25, 10, '2026-10-25 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(32, 5, '2026-10-29 06:00:00', '2026-11-03 18:00:00', '2026-10-29 12:00:00', '2026-11-03 13:00:00', 25, 10, '2026-10-26 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(33, 7, '2026-10-30 06:00:00', '2026-11-01 18:00:00', '2026-10-30 12:00:00', '2026-11-01 13:00:00', 20, 10, '2026-10-27 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(34, 8, '2026-10-30 06:00:00', '2026-11-02 18:00:00', '2026-10-30 12:00:00', '2026-11-02 13:00:00', 20, 10, '2026-10-27 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(35, 6, '2026-11-02 06:00:00', '2026-11-05 18:00:00', '2026-11-02 12:00:00', '2026-11-05 13:00:00', 20, 10, '2026-10-30 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(36, 12, '2026-11-04 06:00:00', '2026-11-08 18:00:00', '2026-11-04 12:00:00', '2026-11-08 13:00:00', 20, 10, '2026-11-01 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(37, 4, '2026-11-05 06:00:00', '2026-11-08 18:00:00', '2026-11-05 12:00:00', '2026-11-08 13:00:00', 20, 10, '2026-11-02 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(38, 11, '2026-11-11 06:00:00', '2026-11-15 18:00:00', '2026-11-11 12:00:00', '2026-11-15 13:00:00', 20, 10, '2026-11-08 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(39, 7, '2026-11-13 06:00:00', '2026-11-15 18:00:00', '2026-11-13 12:00:00', '2026-11-15 13:00:00', 20, 10, '2026-11-10 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(40, 10, '2026-11-14 06:00:00', '2026-11-16 18:00:00', '2026-11-14 12:00:00', '2026-11-16 13:00:00', 20, 10, '2026-11-11 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(41, 15, '2026-11-17 06:00:00', '2026-11-20 18:00:00', '2026-11-17 12:00:00', '2026-11-20 13:00:00', 20, 10, '2026-11-14 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(42, 9, '2026-11-18 06:00:00', '2026-11-21 18:00:00', '2026-11-18 12:00:00', '2026-11-21 13:00:00', 25, 10, '2026-11-15 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(43, 5, '2026-11-19 06:00:00', '2026-11-24 18:00:00', '2026-11-19 12:00:00', '2026-11-24 13:00:00', 25, 10, '2026-11-16 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(44, 14, '2026-11-20 06:00:00', '2026-11-22 18:00:00', '2026-11-20 12:00:00', '2026-11-22 13:00:00', 20, 10, '2026-11-17 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(45, 8, '2026-11-20 06:00:00', '2026-11-23 18:00:00', '2026-11-20 12:00:00', '2026-11-23 13:00:00', 20, 10, '2026-11-17 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(46, 13, '2026-11-21 06:00:00', '2026-11-25 18:00:00', '2026-11-21 12:00:00', '2026-11-25 13:00:00', 20, 10, '2026-11-18 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(47, 6, '2026-11-23 06:00:00', '2026-11-26 18:00:00', '2026-11-23 12:00:00', '2026-11-26 13:00:00', 20, 10, '2026-11-20 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(48, 4, '2026-11-26 06:00:00', '2026-11-29 18:00:00', '2026-11-26 12:00:00', '2026-11-29 13:00:00', 20, 10, '2026-11-23 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(49, 7, '2026-11-27 06:00:00', '2026-11-29 18:00:00', '2026-11-27 12:00:00', '2026-11-29 13:00:00', 20, 10, '2026-11-24 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(50, 12, '2026-12-02 06:00:00', '2026-12-06 18:00:00', '2026-12-02 12:00:00', '2026-12-06 13:00:00', 20, 10, '2026-11-29 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(51, 9, '2026-12-09 06:00:00', '2026-12-12 18:00:00', '2026-12-09 12:00:00', '2026-12-12 13:00:00', 25, 10, '2026-12-06 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(52, 11, '2026-12-09 06:00:00', '2026-12-13 18:00:00', '2026-12-09 12:00:00', '2026-12-13 13:00:00', 20, 10, '2026-12-06 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(53, 5, '2026-12-10 06:00:00', '2026-12-15 18:00:00', '2026-12-10 12:00:00', '2026-12-15 13:00:00', 25, 10, '2026-12-07 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(54, 7, '2026-12-11 06:00:00', '2026-12-13 18:00:00', '2026-12-11 12:00:00', '2026-12-13 13:00:00', 20, 10, '2026-12-08 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(55, 8, '2026-12-11 06:00:00', '2026-12-14 18:00:00', '2026-12-11 12:00:00', '2026-12-14 13:00:00', 20, 10, '2026-12-08 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(56, 10, '2026-12-12 06:00:00', '2026-12-14 18:00:00', '2026-12-12 12:00:00', '2026-12-14 13:00:00', 20, 10, '2026-12-09 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(57, 6, '2026-12-14 06:00:00', '2026-12-17 18:00:00', '2026-12-14 12:00:00', '2026-12-17 13:00:00', 20, 10, '2026-12-11 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(58, 15, '2026-12-15 06:00:00', '2026-12-18 18:00:00', '2026-12-15 12:00:00', '2026-12-18 13:00:00', 20, 10, '2026-12-12 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(59, 4, '2026-12-17 06:00:00', '2026-12-20 18:00:00', '2026-12-17 12:00:00', '2026-12-20 13:00:00', 20, 10, '2026-12-14 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(60, 14, '2026-12-18 06:00:00', '2026-12-20 18:00:00', '2026-12-18 12:00:00', '2026-12-20 13:00:00', 20, 10, '2026-12-15 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(61, 13, '2026-12-19 06:00:00', '2026-12-23 18:00:00', '2026-12-19 12:00:00', '2026-12-23 13:00:00', 20, 10, '2026-12-16 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL),
(62, 7, '2026-12-25 06:00:00', '2026-12-27 18:00:00', '2026-12-25 12:00:00', '2026-12-27 13:00:00', 20, 10, '2026-12-22 06:00:00', NULL, 0, 'open', NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00', NULL, NULL, NULL, 0, NULL, NULL);



CREATE TABLE `tour_schedule_guides` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_schedule_id` bigint UNSIGNED NOT NULL,
  `guide_id` bigint UNSIGNED NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tour_schedule_guides` (`id`, `tour_schedule_id`, `guide_id`, `accepted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(2, 7, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(3, 2, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(4, 12, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(5, 8, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(6, 13, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(7, 18, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(8, 3, 12, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(9, 19, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(10, 20, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(11, 14, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(12, 21, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(13, 9, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(14, 22, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(15, 4, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(16, 23, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(17, 24, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(18, 25, 12, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(19, 26, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(20, 27, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(21, 28, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(22, 29, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(23, 30, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(24, 31, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(25, 16, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(26, 32, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(27, 33, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(28, 34, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(29, 35, 12, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(30, 36, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(31, 37, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(32, 10, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(33, 38, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(34, 5, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(35, 39, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(36, 40, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(37, 41, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(38, 42, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(39, 43, 12, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(40, 44, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(41, 45, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(42, 46, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(43, 47, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(44, 48, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(45, 49, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(46, 17, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(47, 50, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(48, 11, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(49, 51, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(50, 52, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(51, 53, 12, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(52, 54, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(53, 55, 9, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(54, 56, 2, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(55, 57, 4, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(56, 58, 6, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(57, 6, 8, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(58, 59, 10, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(59, 60, 5, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(60, 61, 13, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49'),
(61, 62, 14, NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49');



CREATE TABLE `tour_service` (
  `id` bigint UNSIGNED NOT NULL,
  `tour_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tour_service` (`id`, `tour_id`, `service_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL),
(2, 1, 2, NULL, NULL),
(3, 1, 3, NULL, NULL),
(4, 1, 6, NULL, NULL),
(5, 2, 1, NULL, NULL),
(6, 2, 3, NULL, NULL),
(7, 2, 4, NULL, NULL),
(8, 2, 7, NULL, NULL),
(9, 3, 1, NULL, NULL),
(10, 3, 3, NULL, NULL),
(11, 3, 4, NULL, NULL),
(12, 3, 5, NULL, NULL),
(13, 7, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(14, 7, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(15, 7, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(16, 7, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(17, 7, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(18, 8, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(19, 8, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(20, 8, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(21, 8, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(22, 8, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(23, 8, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(24, 9, 7, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(25, 9, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(26, 9, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(27, 9, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(28, 9, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(29, 9, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(30, 9, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(31, 10, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(32, 10, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(33, 10, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(34, 10, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(35, 10, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(36, 11, 7, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(37, 11, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(38, 11, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(39, 11, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(40, 11, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(41, 11, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(42, 11, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(43, 12, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(44, 12, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(45, 12, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(46, 12, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(47, 12, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(48, 12, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(49, 13, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(50, 13, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(51, 13, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(52, 13, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(53, 13, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(54, 14, 7, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(55, 14, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(56, 14, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(57, 14, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(58, 14, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(59, 14, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(60, 15, 1, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(61, 15, 2, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(62, 15, 3, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(63, 15, 4, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(64, 15, 5, '2026-09-28 09:00:00', '2026-09-28 09:00:00'),
(65, 15, 6, '2026-09-28 09:00:00', '2026-09-28 09:00:00');



CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','guide','customer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `phone`, `address`, `avatar`, `role`, `status`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Admin Demo 1', 'admin@example.test', '2026-09-28 12:36:36', '!demo-password-set-on-restore!', NULL, NULL, NULL, 'admin', 'active', NULL, '2026-09-28 12:36:36', '2026-09-28 12:36:36', NULL),
(2, 'Guide Demo 2', 'guide@example.test', '2026-09-28 12:36:46', '!demo-password-set-on-restore!', '0900000002', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:36', '2026-09-28 12:36:46', NULL),
(3, 'Customer Demo 3', 'customer@example.test', NULL, '!demo-password-set-on-restore!', '0900000003', 'Thông tin demo 3', NULL, 'customer', 'active', NULL, '2026-09-28 12:36:46', '2026-09-28 12:36:46', NULL),
(4, 'Guide Demo 4', 'guide4@example.test', '2026-09-28 12:36:46', '!demo-password-set-on-restore!', '0900000004', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:46', '2026-09-28 12:36:46', NULL),
(5, 'Guide Demo 5', 'guide5@example.test', '2026-09-28 12:36:46', '!demo-password-set-on-restore!', '0900000005', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:46', '2026-09-28 12:36:46', NULL),
(6, 'Guide Demo 6', 'guide6@example.test', '2026-09-28 12:36:46', '!demo-password-set-on-restore!', '0900000006', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:47', '2026-09-28 12:36:47', NULL),
(7, 'Guide Demo 7', 'guide7@example.test', '2026-09-28 12:36:47', '!demo-password-set-on-restore!', '0900000007', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:47', '2026-09-28 12:36:47', NULL),
(8, 'Guide Demo 8', 'guide8@example.test', '2026-09-28 12:36:47', '!demo-password-set-on-restore!', '0900000008', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:47', '2026-09-28 12:36:47', NULL),
(9, 'Guide Demo 9', 'guide9@example.test', '2026-09-28 12:36:47', '!demo-password-set-on-restore!', '0900000009', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:47', '2026-09-28 12:36:47', NULL),
(10, 'Guide Demo 10', 'guide10@example.test', '2026-09-28 12:36:47', '!demo-password-set-on-restore!', '0900000010', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:48', '2026-09-28 12:36:48', NULL),
(11, 'Guide Demo 11', 'guide11@example.test', '2026-09-28 12:36:48', '!demo-password-set-on-restore!', '0900000011', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:48', '2026-09-28 12:36:48', NULL),
(12, 'Guide Demo 12', 'guide12@example.test', '2026-09-28 12:36:48', '!demo-password-set-on-restore!', '0900000012', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:48', '2026-09-28 12:36:48', NULL),
(13, 'Guide Demo 13', 'guide13@example.test', '2026-09-28 12:36:48', '!demo-password-set-on-restore!', '0900000013', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:48', '2026-09-28 12:36:48', NULL),
(14, 'Guide Demo 14', 'guide14@example.test', '2026-09-28 12:36:48', '!demo-password-set-on-restore!', '0900000014', NULL, NULL, 'guide', 'active', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(15, 'Guide Demo 15', 'guide15@example.test', '2026-09-28 12:36:49', '!demo-password-set-on-restore!', '0900000015', NULL, NULL, 'guide', 'inactive', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(16, 'Guide Demo 16', 'guide16@example.test', '2026-09-28 12:36:49', '!demo-password-set-on-restore!', '0900000016', NULL, NULL, 'guide', 'inactive', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(17, 'Customer Demo 17', 'customer17@example.test', NULL, '!demo-password-set-on-restore!', NULL, NULL, NULL, 'customer', 'active', NULL, '2026-09-28 12:36:49', '2026-09-28 12:36:49', NULL),
(18, 'Customer Demo 18', 'customer18@example.test', NULL, '!demo-password-set-on-restore!', NULL, NULL, NULL, 'customer', 'active', NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50', NULL),
(19, 'Customer Demo 19', 'customer19@example.test', NULL, '!demo-password-set-on-restore!', NULL, NULL, NULL, 'customer', 'active', NULL, '2026-09-28 12:36:50', '2026-09-28 12:36:50', NULL);


INSERT INTO `tour_itineraries` (`id`, `tour_id`, `day_number`, `title`, `start_point`, `end_point`, `route_points`, `rest_stops`, `content`, `created_at`, `updated_at`, `images`) VALUES
('39', '1', '1', 'Hà Nội – Hạ Long', 'Hà Nội', 'Hạ Long', 'Hà Nội → Hạ Long', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Khởi hành từ Hà Nội, nhận phòng và dạo biển Bãi Cháy.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1507525428034-b723cf961d3e"]'),
('40', '1', '2', 'Hạ Long – Hạ Long', 'Hạ Long', 'Hạ Long', 'Hạ Long → Hạ Long', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Tham quan vịnh Hạ Long, khám phá hang động và nghỉ ngơi trên bờ.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1507525428034-b723cf961d3e"]'),
('41', '1', '3', 'Hạ Long – Hà Nội', 'Hạ Long', 'Hà Nội', 'Hạ Long → Hà Nội', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng, mua đặc sản địa phương, trả phòng và trở về Hà Nội.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1507525428034-b723cf961d3e"]'),
('42', '2', '1', 'Đà Nẵng – Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng → Đà Nẵng', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Đón khách, tham quan bán đảo Sơn Trà và nghỉ ngơi tại biển Mỹ Khê.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86"]'),
('43', '2', '2', 'Đà Nẵng – Bà Nà Hills', 'Đà Nẵng', 'Bà Nà Hills', 'Đà Nẵng → Bà Nà Hills', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Đi cáp treo, tham quan Cầu Vàng và khu làng Pháp, trở về khách sạn.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86"]'),
('44', '2', '3', 'Đà Nẵng – Hội An', 'Đà Nẵng', 'Hội An', 'Đà Nẵng → Hội An', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Tham quan Ngũ Hành Sơn, đi bộ phố cổ Hội An và thưởng thức món địa phương.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86"]'),
('45', '2', '4', 'Hội An – Đà Nẵng', 'Hội An', 'Đà Nẵng', 'Hội An → Đà Nẵng', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng, trả phòng, tập trung đoàn và đưa khách về điểm đón ban đầu.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86"]'),
('46', '3', '1', 'Hà Nội – Sapa', 'Hà Nội', 'Sapa', 'Hà Nội → Sapa', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Khởi hành đi Sapa, nhận phòng và tham quan bản Cát Cát.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1570366583862-f91883984fde"]'),
('47', '3', '2', 'Sapa – Hà Nội', 'Sapa', 'Hà Nội', 'Sapa → Hà Nội', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Tham quan Fansipan, ăn trưa, trả phòng và trở về Hà Nội.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://images.unsplash.com/photo-1570366583862-f91883984fde"]'),
('48', '4', '4', 'Chùa Bái Đính – TP. Hồ Chí Minh', 'Chùa Bái Đính', 'TP. Hồ Chí Minh', 'Chùa Bái Đính → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_4681_trang-an-3.webp"]'),
('49', '5', '6', 'Ninh Bình – TP. Hồ Chí Minh', 'Ninh Bình', 'TP. Hồ Chí Minh', 'Ninh Bình → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_3966_view-of-sapa-town.webp"]'),
('50', '6', '4', 'Yên Tử – TP. Hồ Chí Minh', 'Yên Tử', 'TP. Hồ Chí Minh', 'Yên Tử → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_9810_thap-tram-huong.webp"]'),
('51', '7', '3', 'Bà Nà – TP. Hồ Chí Minh', 'Bà Nà', 'TP. Hồ Chí Minh', 'Bà Nà → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_11802_ganh-da-dia.webp"]'),
('52', '8', '4', 'Gành Đá Dĩa – TP. Hồ Chí Minh', 'Gành Đá Dĩa', 'TP. Hồ Chí Minh', 'Gành Đá Dĩa → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_9386_bai-bien-nha-trang-2.webp"]'),
('53', '9', '4', 'VinWonders – TP. Hồ Chí Minh', 'VinWonders', 'TP. Hồ Chí Minh', 'VinWonders → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13564_vinwonders.webp"]'),
('54', '10', '3', 'Đà Lạt – Cần Thơ', 'Đà Lạt', 'Cần Thơ', 'Đà Lạt → Cần Thơ', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13063_trai-nghiem-tour-song-ba-lai-minh-hoa-6.webp"]'),
('55', '11', '5', 'Bạc Liêu – Vinh', 'Bạc Liêu', 'Vinh', 'Bạc Liêu → Vinh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_13010_con-son-vuon-trai-cay.webp"]'),
('56', '12', '5', 'Bạc Liêu – TP. Hồ Chí Minh', 'Bạc Liêu', 'TP. Hồ Chí Minh', 'Bạc Liêu → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_7324_quang-truong-lam-vien-1.webp"]'),
('57', '13', '5', 'Cần Thơ – TP. Hồ Chí Minh', 'Cần Thơ', 'TP. Hồ Chí Minh', 'Cần Thơ → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__0_8392_mu-cang-chai-yen-bai.webp"]'),
('58', '14', '3', 'Thới Sơn – TP. Hồ Chí Minh', 'Thới Sơn', 'TP. Hồ Chí Minh', 'Thới Sơn → TP. Hồ Chí Minh', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__2_6777_cat-cat.webp"]'),
('59', '15', '4', 'VinWonders – Hà Nội', 'VinWonders', 'Hà Nội', 'VinWonders → Hà Nội', 'Điểm nghỉ trên đường theo hướng dẫn của hướng dẫn viên', 'Ăn sáng tại khách sạn, tự do mua đặc sản địa phương. Trả phòng, ăn trưa và tập trung đoàn theo hướng dẫn của hướng dẫn viên. Di chuyển về điểm đón ban đầu, kiểm tra hành lý và kết thúc chuyến đi.', '2026-09-28 16:00:00', '2026-09-28 16:00:00', '["https://s3-cmc.travel.com.vn/vtv-image/Images/Destination/tf__1_10189_vinwonders.webp"]');
INSERT INTO `itinerary_checkpoints` (`id`, `tour_itinerary_id`, `name`, `description`, `latitude`, `longitude`, `sequence`, `is_required_photo`, `created_at`, `updated_at`) VALUES
('13', '10', 'Sapa', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('14', '10', 'Fansipan', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('15', '11', 'Fansipan', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('16', '11', 'Yên Tử', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('17', '12', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('18', '12', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('19', '13', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('20', '13', 'Bà Nà', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('21', '14', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('22', '14', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('23', '15', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('24', '15', 'Phú Yên', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('25', '16', 'Phú Yên', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('26', '16', 'Gành Đá Dĩa', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('27', '17', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('28', '17', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('29', '18', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('30', '18', 'I-Resort', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('31', '19', 'I-Resort', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('32', '19', 'VinWonders', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('33', '20', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('34', '20', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('35', '21', 'Nha Trang', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('36', '21', 'Đà Lạt', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('37', '22', 'Vinh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('38', '22', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('39', '23', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('40', '23', 'Cà Mau', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('41', '24', 'Cà Mau', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('42', '24', 'Đất Mũi', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('43', '25', 'Đất Mũi', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('44', '25', 'Bạc Liêu', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('45', '26', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('46', '26', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('47', '27', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('48', '27', 'Cà Mau', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('49', '28', 'Cà Mau', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('50', '28', 'Đất Mũi', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('51', '29', 'Đất Mũi', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('52', '29', 'Bạc Liêu', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('53', '30', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('54', '30', 'Châu Đốc', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('55', '31', 'Châu Đốc', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('56', '31', 'Núi Cấm', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('57', '32', 'Núi Cấm', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('58', '32', 'Rừng Tràm Trà Sư', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('59', '33', 'Rừng Tràm Trà Sư', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('60', '33', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('61', '34', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('62', '34', 'Mỹ Tho', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('63', '35', 'Mỹ Tho', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('64', '35', 'Thới Sơn', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('65', '36', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('66', '36', 'Phú Quốc', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('67', '37', 'Phú Quốc', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('68', '37', 'Hòn Thơm', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('69', '38', 'Hòn Thơm', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('70', '38', 'VinWonders', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('71', '39', 'Hà Nội', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('72', '39', 'Hạ Long', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('73', '40', 'Hạ Long', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('74', '40', 'Hạ Long', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('75', '41', 'Hạ Long', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('76', '41', 'Hà Nội', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('77', '42', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('78', '42', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('79', '43', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('80', '43', 'Bà Nà Hills', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('81', '44', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('82', '44', 'Hội An', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('83', '45', 'Hội An', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('84', '45', 'Đà Nẵng', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('85', '46', 'Hà Nội', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('86', '46', 'Sapa', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('87', '47', 'Sapa', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('88', '47', 'Hà Nội', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('89', '48', 'Chùa Bái Đính', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('90', '48', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('91', '49', 'Ninh Bình', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('92', '49', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('93', '50', 'Yên Tử', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('94', '50', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('95', '51', 'Bà Nà', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('96', '51', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('97', '52', 'Gành Đá Dĩa', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('98', '52', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('99', '53', 'VinWonders', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('100', '53', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('101', '54', 'Đà Lạt', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('102', '54', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('103', '55', 'Bạc Liêu', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('104', '55', 'Vinh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('105', '56', 'Bạc Liêu', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('106', '56', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('107', '57', 'Cần Thơ', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('108', '57', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('109', '58', 'Thới Sơn', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('110', '58', 'TP. Hồ Chí Minh', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('111', '59', 'VinWonders', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '1', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00'),
('112', '59', 'Hà Nội', 'Tập trung đoàn và kiểm tra danh sách hành khách.', NULL, NULL, '2', '0', '2026-09-28 16:00:00', '2026-09-28 16:00:00');
ALTER TABLE `ai_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ai_chat_messages_user_id_foreign` (`user_id`),
  ADD KEY `ai_chat_messages_conversation_token_index` (`conversation_token`);

ALTER TABLE `ai_knowledge_chunks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ai_knowledge_chunks_chunk_key_unique` (`chunk_key`),
  ADD KEY `ai_knowledge_chunks_source_type_source_id_index` (`source_type`,`source_id`);

ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bookings_public_token_unique` (`public_token`),
  ADD KEY `bookings_tour_schedule_id_foreign` (`tour_schedule_id`),
  ADD KEY `bookings_tour_id_status_index` (`tour_id`,`status`),
  ADD KEY `bookings_customer_id_status_index` (`customer_id`,`status`),
  ADD KEY `bookings_guest_id_status_index` (`guest_id`,`status`),
  ADD KEY `bookings_departure_date_status_index` (`departure_date`,`status`),
  ADD KEY `bookings_vnpay_transaction_no_index` (`vnpay_transaction_no`),
  ADD KEY `bookings_discount_code_id_foreign` (`discount_code_id`),
  ADD KEY `bookings_status_expires_at_index` (`status`,`expires_at`),
  ADD KEY `bookings_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `bookings_seats_released_by_foreign` (`seats_released_by`),
  ADD KEY `idx_bookings_status_seats_released` (`status`,`seats_released`),
  ADD KEY `bookings_cancellation_policy_id_foreign` (`cancellation_policy_id`),
  ADD KEY `bookings_split_from_booking_id_foreign` (`split_from_booking_id`),
  ADD KEY `bookings_group_booking_request_id_foreign` (`group_booking_request_id`);

ALTER TABLE `booking_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_audit_logs_actor_id_foreign` (`actor_id`),
  ADD KEY `booking_audit_logs_booking_id_created_at_index` (`booking_id`,`created_at`),
  ADD KEY `booking_audit_logs_action_created_at_index` (`action`,`created_at`);

ALTER TABLE `booking_change_proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_change_proposals_booking_id_foreign` (`booking_id`),
  ADD KEY `booking_change_proposals_admin_id_foreign` (`admin_id`),
  ADD KEY `booking_change_proposals_from_schedule_id_foreign` (`from_schedule_id`),
  ADD KEY `booking_change_proposals_to_schedule_id_foreign` (`to_schedule_id`);

ALTER TABLE `booking_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_change_requests_requested_by_foreign` (`requested_by`),
  ADD KEY `booking_change_requests_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `booking_change_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `booking_change_requests_booking_id_status_index` (`booking_id`,`status`);

ALTER TABLE `booking_checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_checkins_booking_id_tour_itinerary_id_unique` (`booking_id`,`tour_itinerary_id`),
  ADD KEY `booking_checkins_tour_itinerary_id_foreign` (`tour_itinerary_id`),
  ADD KEY `booking_checkins_guide_id_foreign` (`guide_id`);

ALTER TABLE `booking_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_contracts_booking_id_unique` (`booking_id`),
  ADD UNIQUE KEY `booking_contracts_contract_number_unique` (`contract_number`),
  ADD KEY `booking_contracts_issued_by_foreign` (`issued_by`);

ALTER TABLE `booking_passengers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_passengers_booking_id_foreign` (`booking_id`);

ALTER TABLE `booking_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_payments_recorded_by_foreign` (`recorded_by`),
  ADD KEY `booking_payments_booking_id_index` (`booking_id`),
  ADD KEY `booking_payments_booking_surcharge_id_foreign` (`booking_surcharge_id`);

ALTER TABLE `booking_surcharges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_surcharges_approved_by_foreign` (`approved_by`),
  ADD KEY `booking_surcharges_booking_id_status_index` (`booking_id`,`status`),
  ADD KEY `booking_surcharges_schedule_incident_id_index` (`schedule_incident_id`);

ALTER TABLE `booking_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_transfers_from_schedule_id_foreign` (`from_schedule_id`),
  ADD KEY `booking_transfers_from_tour_id_foreign` (`from_tour_id`),
  ADD KEY `booking_transfers_to_tour_id_foreign` (`to_tour_id`),
  ADD KEY `booking_transfers_approved_by_foreign` (`approved_by`),
  ADD KEY `booking_transfers_booking_id_created_at_index` (`booking_id`,`created_at`),
  ADD KEY `booking_transfers_to_schedule_id_created_at_index` (`to_schedule_id`,`created_at`),
  ADD KEY `booking_transfers_contact_log_id_foreign` (`contact_log_id`);

ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

ALTER TABLE `cancellation_policies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cancellation_policies_effective_from_index` (`effective_from`);

ALTER TABLE `cancellation_policy_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_policy_rules_days` (`cancellation_policy_id`,`min_days_before`);

ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_name_unique` (`name`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

ALTER TABLE `category_tour`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_tour_tour_id_category_id_unique` (`tour_id`,`category_id`),
  ADD KEY `category_tour_category_id_foreign` (`category_id`);

ALTER TABLE `checkpoint_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checkpoint_photos_tour_schedule_id_foreign` (`tour_schedule_id`),
  ADD KEY `checkpoint_photos_tour_itinerary_id_foreign` (`tour_itinerary_id`),
  ADD KEY `checkpoint_photos_guide_id_foreign` (`guide_id`),
  ADD KEY `checkpoint_photos_itinerary_checkpoint_id_foreign` (`itinerary_checkpoint_id`);

ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_messages_handled_by_foreign` (`handled_by`),
  ADD KEY `contact_messages_status_created_at_index` (`status`,`created_at`);

ALTER TABLE `customer_contact_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_contact_logs_contacted_by_foreign` (`contacted_by`),
  ADD KEY `customer_contact_logs_booking_id_contacted_at_index` (`booking_id`,`contacted_at`);

ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `discount_codes_code_unique` (`code`),
  ADD KEY `discount_codes_code_is_active_index` (`code`,`is_active`),
  ADD KEY `discount_codes_starts_at_expires_at_index` (`starts_at`,`expires_at`);

ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

ALTER TABLE `group_booking_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_booking_requests_public_token_unique` (`public_token`),
  ADD KEY `group_booking_requests_customer_id_foreign` (`customer_id`),
  ADD KEY `group_booking_requests_quoted_by_foreign` (`quoted_by`),
  ADD KEY `group_booking_requests_decided_by_foreign` (`decided_by`),
  ADD KEY `group_booking_requests_status_index` (`status`),
  ADD KEY `group_booking_requests_tour_schedule_id_index` (`tour_schedule_id`),
  ADD KEY `group_booking_requests_tour_id_foreign` (`tour_id`);

ALTER TABLE `guide_assignment_declines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guide_assignment_declines_tour_schedule_id_declined_at_index` (`tour_schedule_id`,`declined_at`),
  ADD KEY `guide_assignment_declines_guide_id_index` (`guide_id`);

ALTER TABLE `guide_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guide_categories_user_id_category_id_unique` (`user_id`,`category_id`),
  ADD KEY `guide_categories_category_id_foreign` (`category_id`);

ALTER TABLE `guide_handovers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guide_handovers_created_by_foreign` (`created_by`),
  ADD KEY `guide_handovers_tour_schedule_id_handed_over_at_index` (`tour_schedule_id`,`handed_over_at`),
  ADD KEY `guide_handovers_to_guide_id_index` (`to_guide_id`),
  ADD KEY `guide_handovers_from_guide_id_index` (`from_guide_id`);

ALTER TABLE `guide_handover_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guide_handover_requests_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `guide_handover_requests_guide_handover_id_foreign` (`guide_handover_id`),
  ADD KEY `guide_handover_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `guide_handover_requests_tour_schedule_id_index` (`tour_schedule_id`),
  ADD KEY `guide_handover_requests_requested_by_index` (`requested_by`);

ALTER TABLE `guide_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guide_profiles_user_id_unique` (`user_id`);

ALTER TABLE `incident_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_photos_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `incident_photos_schedule_incident_id_index` (`schedule_incident_id`);

ALTER TABLE `itinerary_checkpoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `itinerary_checkpoints_tour_itinerary_id_sequence_index` (`tour_itinerary_id`,`sequence`);

ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `newsletter_subscribers_email_unique` (`email`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

ALTER TABLE `passenger_checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `passenger_checkin_unique` (`booking_passenger_id`,`itinerary_checkpoint_id`),
  ADD KEY `passenger_checkins_itinerary_checkpoint_id_foreign` (`itinerary_checkpoint_id`),
  ADD KEY `passenger_checkins_checked_by_foreign` (`checked_by`),
  ADD KEY `idx_checkins_schedule_checkpoint` (`tour_schedule_id`,`itinerary_checkpoint_id`);

ALTER TABLE `passenger_checkin_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `passenger_checkin_histories_passenger_checkin_id_foreign` (`passenger_checkin_id`),
  ADD KEY `passenger_checkin_histories_changed_by_foreign` (`changed_by`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_logs_booking_id_provider_index` (`booking_id`,`provider`),
  ADD KEY `payment_logs_transaction_no_index` (`transaction_no`);

ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviews_user_id_foreign` (`user_id`),
  ADD KEY `reviews_moderated_by_foreign` (`moderated_by`),
  ADD KEY `reviews_replied_by_foreign` (`replied_by`),
  ADD KEY `reviews_tour_id_status_index` (`tour_id`,`status`),
  ADD KEY `reviews_status_index` (`status`);

ALTER TABLE `schedule_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_audit_logs_actor_id_foreign` (`actor_id`),
  ADD KEY `schedule_audit_logs_tour_schedule_id_created_at_index` (`tour_schedule_id`,`created_at`),
  ADD KEY `schedule_audit_logs_action_created_at_index` (`action`,`created_at`);

ALTER TABLE `schedule_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_incidents_tour_itinerary_id_foreign` (`tour_itinerary_id`),
  ADD KEY `schedule_incidents_reported_by_foreign` (`reported_by`),
  ADD KEY `schedule_incidents_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `schedule_incidents_tour_schedule_id_occurred_at_index` (`tour_schedule_id`,`occurred_at`),
  ADD KEY `schedule_incidents_status_severity_index` (`status`,`severity`);

ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `services_name_unique` (`name`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

ALTER TABLE `tours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tours_slug_unique` (`slug`),
  ADD KEY `tours_admin_id_status_index` (`admin_id`,`status`),
  ADD KEY `tours_cancellation_policy_id_foreign` (`cancellation_policy_id`);

ALTER TABLE `tour_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_images_tour_id_foreign` (`tour_id`);

ALTER TABLE `tour_itineraries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_itineraries_tour_id_foreign` (`tour_id`);

ALTER TABLE `tour_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_schedules_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `tour_schedules_merged_into_schedule_id_foreign` (`merged_into_schedule_id`),
  ADD KEY `idx_schedules_tour_status_start` (`tour_id`,`status`,`start_date`),
  ADD KEY `idx_schedules_status_deadline` (`status`,`booking_deadline`);

ALTER TABLE `tour_schedule_guides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_schedule_guide` (`tour_schedule_id`,`guide_id`),
  ADD KEY `idx_schedule_guides_guide` (`guide_id`);

ALTER TABLE `tour_service`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tour_service_tour_id_service_id_unique` (`tour_id`,`service_id`),
  ADD KEY `tour_service_service_id_foreign` (`service_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);


ALTER TABLE `ai_chat_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_knowledge_chunks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `bookings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `booking_audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `booking_change_proposals`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `booking_change_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `booking_checkins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `booking_contracts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `booking_passengers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

ALTER TABLE `booking_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

ALTER TABLE `booking_surcharges`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `booking_transfers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cancellation_policies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `cancellation_policy_rules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `category_tour`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

ALTER TABLE `checkpoint_photos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `contact_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_contact_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `discount_codes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `group_booking_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `guide_assignment_declines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `guide_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `guide_handovers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `guide_handover_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `guide_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `incident_photos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `itinerary_checkpoints`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

ALTER TABLE `newsletter_subscribers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `passenger_checkins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `passenger_checkin_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payment_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `reviews`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `schedule_audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `schedule_incidents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `services`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `tours`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

ALTER TABLE `tour_images`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

ALTER TABLE `tour_itineraries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

ALTER TABLE `tour_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

ALTER TABLE `tour_schedule_guides`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

ALTER TABLE `tour_service`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;


ALTER TABLE `ai_chat_messages`
  ADD CONSTRAINT `ai_chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_cancellation_policy_id_foreign` FOREIGN KEY (`cancellation_policy_id`) REFERENCES `cancellation_policies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_discount_code_id_foreign` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_group_booking_request_id_foreign` FOREIGN KEY (`group_booking_request_id`) REFERENCES `group_booking_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_seats_released_by_foreign` FOREIGN KEY (`seats_released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_split_from_booking_id_foreign` FOREIGN KEY (`split_from_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `bookings_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL;

ALTER TABLE `booking_audit_logs`
  ADD CONSTRAINT `booking_audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_audit_logs_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

ALTER TABLE `booking_change_proposals`
  ADD CONSTRAINT `booking_change_proposals_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `booking_change_proposals_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_change_proposals_from_schedule_id_foreign` FOREIGN KEY (`from_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_change_proposals_to_schedule_id_foreign` FOREIGN KEY (`to_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL;

ALTER TABLE `booking_change_requests`
  ADD CONSTRAINT `booking_change_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_change_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_change_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `booking_checkins`
  ADD CONSTRAINT `booking_checkins_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_checkins_guide_id_foreign` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_checkins_tour_itinerary_id_foreign` FOREIGN KEY (`tour_itinerary_id`) REFERENCES `tour_itineraries` (`id`) ON DELETE CASCADE;

ALTER TABLE `booking_contracts`
  ADD CONSTRAINT `booking_contracts_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_contracts_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `booking_passengers`
  ADD CONSTRAINT `booking_passengers_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

ALTER TABLE `booking_payments`
  ADD CONSTRAINT `booking_payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_payments_booking_surcharge_id_foreign` FOREIGN KEY (`booking_surcharge_id`) REFERENCES `booking_surcharges` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `booking_surcharges`
  ADD CONSTRAINT `booking_surcharges_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_surcharges_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_surcharges_schedule_incident_id_foreign` FOREIGN KEY (`schedule_incident_id`) REFERENCES `schedule_incidents` (`id`) ON DELETE CASCADE;

ALTER TABLE `booking_transfers`
  ADD CONSTRAINT `booking_transfers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_transfers_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_transfers_contact_log_id_foreign` FOREIGN KEY (`contact_log_id`) REFERENCES `customer_contact_logs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_transfers_from_schedule_id_foreign` FOREIGN KEY (`from_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_transfers_from_tour_id_foreign` FOREIGN KEY (`from_tour_id`) REFERENCES `tours` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_transfers_to_schedule_id_foreign` FOREIGN KEY (`to_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_transfers_to_tour_id_foreign` FOREIGN KEY (`to_tour_id`) REFERENCES `tours` (`id`) ON DELETE SET NULL;

ALTER TABLE `cancellation_policy_rules`
  ADD CONSTRAINT `cancellation_policy_rules_cancellation_policy_id_foreign` FOREIGN KEY (`cancellation_policy_id`) REFERENCES `cancellation_policies` (`id`) ON DELETE CASCADE;

ALTER TABLE `category_tour`
  ADD CONSTRAINT `category_tour_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_tour_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

ALTER TABLE `checkpoint_photos`
  ADD CONSTRAINT `checkpoint_photos_guide_id_foreign` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `checkpoint_photos_itinerary_checkpoint_id_foreign` FOREIGN KEY (`itinerary_checkpoint_id`) REFERENCES `itinerary_checkpoints` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `checkpoint_photos_tour_itinerary_id_foreign` FOREIGN KEY (`tour_itinerary_id`) REFERENCES `tour_itineraries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `checkpoint_photos_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `contact_messages`
  ADD CONSTRAINT `contact_messages_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `customer_contact_logs`
  ADD CONSTRAINT `customer_contact_logs_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_contact_logs_contacted_by_foreign` FOREIGN KEY (`contacted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `group_booking_requests`
  ADD CONSTRAINT `group_booking_requests_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `group_booking_requests_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `group_booking_requests_quoted_by_foreign` FOREIGN KEY (`quoted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `group_booking_requests_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `group_booking_requests_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `guide_assignment_declines`
  ADD CONSTRAINT `guide_assignment_declines_guide_id_foreign` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guide_assignment_declines_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `guide_categories`
  ADD CONSTRAINT `guide_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guide_categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `guide_handovers`
  ADD CONSTRAINT `guide_handovers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `guide_handovers_from_guide_id_foreign` FOREIGN KEY (`from_guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guide_handovers_to_guide_id_foreign` FOREIGN KEY (`to_guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guide_handovers_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `guide_handover_requests`
  ADD CONSTRAINT `guide_handover_requests_guide_handover_id_foreign` FOREIGN KEY (`guide_handover_id`) REFERENCES `guide_handovers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `guide_handover_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guide_handover_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `guide_handover_requests_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `guide_profiles`
  ADD CONSTRAINT `guide_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `incident_photos`
  ADD CONSTRAINT `incident_photos_schedule_incident_id_foreign` FOREIGN KEY (`schedule_incident_id`) REFERENCES `schedule_incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incident_photos_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `itinerary_checkpoints`
  ADD CONSTRAINT `itinerary_checkpoints_tour_itinerary_id_foreign` FOREIGN KEY (`tour_itinerary_id`) REFERENCES `tour_itineraries` (`id`) ON DELETE CASCADE;

ALTER TABLE `passenger_checkins`
  ADD CONSTRAINT `passenger_checkins_booking_passenger_id_foreign` FOREIGN KEY (`booking_passenger_id`) REFERENCES `booking_passengers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `passenger_checkins_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `passenger_checkins_itinerary_checkpoint_id_foreign` FOREIGN KEY (`itinerary_checkpoint_id`) REFERENCES `itinerary_checkpoints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `passenger_checkins_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `passenger_checkin_histories`
  ADD CONSTRAINT `passenger_checkin_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `passenger_checkin_histories_passenger_checkin_id_foreign` FOREIGN KEY (`passenger_checkin_id`) REFERENCES `passenger_checkins` (`id`) ON DELETE CASCADE;

ALTER TABLE `payment_logs`
  ADD CONSTRAINT `payment_logs_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_moderated_by_foreign` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_replied_by_foreign` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `schedule_audit_logs`
  ADD CONSTRAINT `schedule_audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_audit_logs_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL;

ALTER TABLE `schedule_incidents`
  ADD CONSTRAINT `schedule_incidents_reported_by_foreign` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_incidents_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_incidents_tour_itinerary_id_foreign` FOREIGN KEY (`tour_itinerary_id`) REFERENCES `tour_itineraries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_incidents_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `tours`
  ADD CONSTRAINT `tours_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tours_cancellation_policy_id_foreign` FOREIGN KEY (`cancellation_policy_id`) REFERENCES `cancellation_policies` (`id`) ON DELETE SET NULL;

ALTER TABLE `tour_images`
  ADD CONSTRAINT `tour_images_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

ALTER TABLE `tour_itineraries`
  ADD CONSTRAINT `tour_itineraries_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

ALTER TABLE `tour_schedules`
  ADD CONSTRAINT `tour_schedules_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tour_schedules_merged_into_schedule_id_foreign` FOREIGN KEY (`merged_into_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tour_schedules_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

ALTER TABLE `tour_schedule_guides`
  ADD CONSTRAINT `tour_schedule_guides_guide_id_foreign` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_schedule_guides_tour_schedule_id_foreign` FOREIGN KEY (`tour_schedule_id`) REFERENCES `tour_schedules` (`id`) ON DELETE CASCADE;

ALTER TABLE `tour_service`
  ADD CONSTRAINT `tour_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_service_tour_id_foreign` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
