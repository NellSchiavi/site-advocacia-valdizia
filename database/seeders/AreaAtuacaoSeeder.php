<?php

namespace Database\Seeders;

use App\Models\AreaAtuacao;
use Illuminate\Database\Seeder;

class AreaAtuacaoSeeder extends Seeder
{
    public function run(): void
    {
        // Ordem canônica usada em todo o site: Previdenciário, Cível, Família.
        $areas = [
            [
                'slug' => 'direito-previdenciario',
                'nome' => 'Direito Previdenciário',
                'descricao' => 'Aposentadorias, benefícios do INSS, auxílio-doença, revisão de benefícios e demais questões previdenciárias.',
                'icone' => 'user',
                'ordem' => 1,
            ],
            [
                'slug' => 'direito-civil',
                'nome' => 'Direito Civil',
                'descricao' => 'Contratos, indenizações, responsabilidade civil, direitos do consumidor e demais questões cíveis.',
                'icone' => 'document-text',
                'ordem' => 2,
            ],
            [
                'slug' => 'direito-de-familia',
                'nome' => 'Direito de Família',
                'descricao' => 'Divórcio, pensão alimentícia, guarda, regulamentação de visitas, inventário e outras demandas familiares.',
                'icone' => 'users',
                'ordem' => 3,
            ],
        ];

        foreach ($areas as $area) {
            AreaAtuacao::query()->updateOrCreate(['slug' => $area['slug']], $area);
        }
    }
}
