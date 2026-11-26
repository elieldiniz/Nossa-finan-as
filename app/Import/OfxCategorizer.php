<?php

namespace App\Import;

/** Sugere a categoria de uma compra pelo nome do estabelecimento (a pessoa pode trocar na prévia). */
final class OfxCategorizer
{
    /** @var array<string, array<int, string>> categoria => trechos do nome (minúsculos) */
    private const RULES = [
        'Mercado' => ['supermercado', 'mercado', 'taimax', 'tai max', 'jeeda', 'acougue', 'açougue', 'atacad', 'distribuidor', 'padaria', 'hortifruti'],
        'Transporte' => ['posto', 'combust', 'uber', '99', 'estaciona', 'pedagio', 'pedágio', 'gp motos', 'moto'],
        'Saúde' => ['farma', 'drogaria', 'rd farma', 'hospital', 'clinica', 'clínica', 'dentist', 'wellhub', 'academia'],
        'Assinatura' => ['spotify', 'netflix', 'crunchyro', 'google', 'anthropic', 'claude', 'amazonprime', 'amazon prime', 'laravel', 'digitalocean', 'webshare', 'locarmais', 'chatgpt', 'openai', 'icloud', 'youtube'],
        'Lazer' => ['restaurante', 'lanchonete', 'pastelaria', 'cinema', 'parque', 'bar ', 'ifood', 'pizza', 'sorvete', 'floricultura', 'skina', 'espet'],
        'Educação' => ['eduzz', 'curso', 'faculdade', 'escola', 'udemy', 'alura'],
    ];

    public static function suggest(string $memo): string
    {
        $text = mb_strtolower($memo);

        foreach (self::RULES as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $category;
                }
            }
        }

        return 'Outros';
    }
}
