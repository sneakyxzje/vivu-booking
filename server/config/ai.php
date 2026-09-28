<?php

return [

    /*
    | Khai ở config chứ không gọi env() trong service: sau `config:cache` thì
    | env() ngoài thư mục config/ trả về null.
    |
    | chat_model trả lời khách, embedding_model biến chữ thành vector — hai model
    | khác nhau cho hai việc khác nhau.
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4.1-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),

        // Đổi số chiều là phải chạy lại `ai:index --force`: vector cũ không so
        // sánh được với vector mới.
        'embedding_dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),

        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

    /*
    | top_k: số đoạn tri thức đưa vào mỗi câu hỏi.
    | lexical_weight: trọng số của phép so khớp từ khóa bên cạnh so khớp ngữ nghĩa.
    | min_score: dưới ngưỡng này coi như không liên quan, bỏ đi.
    */

    'retrieval' => [
        'top_k' => (int) env('AI_RETRIEVAL_TOP_K', 6),
        'lexical_weight' => (float) env('AI_RETRIEVAL_LEXICAL_WEIGHT', 0.35),
        'min_score' => (float) env('AI_RETRIEVAL_MIN_SCORE', 0.15),
    ],

    'chat' => [
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 900),
        'max_tool_rounds' => (int) env('AI_MAX_TOOL_ROUNDS', 4),
        'history_turns' => (int) env('AI_HISTORY_TURNS', 8),
        'max_question_length' => (int) env('AI_MAX_QUESTION_LENGTH', 1000),
    ],

];
