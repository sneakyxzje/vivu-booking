import axios from "axios";
import api from "./api";
import { extractObject } from "@/utils/apiHelpers";

/**
 * Trợ lý ảo tư vấn tour.
 *
 * Giao diện chỉ cầm `conversation_token`; các lượt trước nằm ở máy chủ, vì lượt của
 * trợ lý mà do trình duyệt gửi lên thì ai cũng bịa được.
 */

export interface ChatTourCard {
  id: number;
  slug: string;
  title: string;
  thumbnail: string | null;
  adult_price: number;
  number_of_days: number;
  number_of_nights: number;
  rating: number | null;
}

export interface ChatReply {
  conversation_token: string;
  answer: string;
  tours: ChatTourCard[];
}

export class ChatError extends Error {}

export const chatService = {
  ask: async (message: string, conversationToken: string | null): Promise<ChatReply> => {
    try {
      const response = await api.post("/chat", {
        message,
        conversation_token: conversationToken ?? undefined,
      });

      const reply = extractObject<ChatReply>(response);

      if (!reply) {
        throw new ChatError("Mình chưa nhận được câu trả lời, bạn thử lại nhé.");
      }

      return { ...reply, tours: reply.tours ?? [] };
    } catch (error) {
      if (!axios.isAxiosError(error)) {
        throw error instanceof ChatError
          ? error
          : new ChatError("Mình chưa gửi được câu hỏi, bạn kiểm tra kết nối mạng giúp nhé.");
      }

      const status = error.response?.status;
      const serverMessage = (error.response?.data as { message?: string } | undefined)?.message;

      // Chạm hạn mức cần một câu riêng, nếu không khách sẽ bấm lại liên tục — đúng
      // thứ hạn mức sinh ra để chặn.
      if (status === 429) {
        throw new ChatError("Bạn hỏi hơi nhanh, chờ mình một chút rồi hỏi tiếp nhé.");
      }

      throw new ChatError(
        serverMessage ?? "Trợ lý đang bận, bạn thử lại sau ít phút giúp mình nhé.",
      );
    }
  },
};

export default chatService;
