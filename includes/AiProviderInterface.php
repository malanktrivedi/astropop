<?php
declare(strict_types=1);

interface AiProviderInterface
{
    /**
     * @param array<string,mixed> $context
     * @param array<int,array<string,mixed>> $history
     * @return array{reply:string,model:string,input_tokens:int|null,output_tokens:int|null,raw:array<string,mixed>}
     */
    public function chat(array $context, array $history, string $message): array;
}
