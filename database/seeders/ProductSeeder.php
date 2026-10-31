<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $shortDescription = 'AOD-9604 is a synthetic peptide derived from the C-terminal region of human growth hormone (amino acids 176-191), developed through metabolic research.';

        $description = '<p>AOD-9604 is a synthetic peptide derived from the C-terminal region of human growth hormone (amino acids 176-191). Developed through metabolic research, it has become one of the most widely recognised peptides in its class due to its specialised structure and extensive scientific investigation. Designed to explore specific biological pathways without replicating the broader activity of full-length growth hormone, AOD-9604 continues to be a subject of interest within laboratory and research environments.</p>
<p>Manufactured to high purity standards, AOD-9604 is valued for its quality, consistency, and reliable production. Its well-characterised molecular structure and long history of research have established it as a popular choice among researchers seeking premium-grade peptides. Every batch is produced using rigorous quality control processes to help ensure purity and product integrity.</p>

<h2>Key Features</h2>
<ul>
<li><strong>Targeted Peptide Design</strong> — AOD-9604 is based on the 176-191 amino acid fragment of human growth hormone, a region selected for its unique biological properties and scientific relevance.</li>
<li><strong>Extensively Researched</strong> — The peptide has been investigated in numerous laboratory and clinical studies, making it one of the most recognised compounds in metabolic peptide research.</li>
<li><strong>High-Purity Manufacturing</strong> — Produced using stringent quality standards and manufacturing processes to deliver a consistent, premium-quality research peptide.</li>
<li><strong>Structurally Distinct</strong> — Unlike full-length human growth hormone, AOD-9604 is a specific peptide fragment with its own unique molecular profile, making it a separate area of scientific investigation.</li>
<li><strong>Metabolic Research Interest</strong> — Scientific studies have explored its interaction with pathways involved in lipid metabolism, adipose tissue biology, and energy regulation, contributing to ongoing research in these fields.</li>
<li><strong>Reliable Batch Consistency</strong> — Manufactured with rigorous quality control measures to help ensure each batch meets high standards of purity and consistency.</li>
<li><strong>Trusted by the Research Community</strong> — AOD-9604 has earned widespread recognition among researchers due to its well-documented scientific background and established research history.</li>
<li><strong>Securely Packaged for Quality</strong> — Carefully packaged to help maintain product integrity during storage and transportation, supporting the preservation of peptide quality.</li>
</ul>';

        Product::updateOrCreate(
            ['slug' => 'aod-9604-10mg'],
            [
                'name' => 'AOD-9604 10mg',
                'slug' => 'aod-9604-10mg',
                'sku' => 'AOD-9604-10MG',
                'short_description' => $shortDescription,
                'description' => $description,
                'base_price' => 29.50,
                'sale_price' => null,
                'cost_price' => null,
                'discount_type' => 'none',
                'discount_value' => 0,
                'stock_status' => 'in_stock',
                'stock_quantity' => 100,
                'weight' => 5.00,
                'length' => null,
                'width' => null,
                'height' => null,
                'is_active' => true,
                'is_featured' => false,
                'is_new' => true,
                'return_allowed' => false,
                'meta_title' => 'AOD-9604 10mg — Research Peptide | PoweredIn Peptides',
                'meta_description' => 'AOD-9604 10mg — a synthetic peptide derived from the C-terminal region of human growth hormone (amino acids 176-191). High-purity manufacturing, reliable batch consistency.',
                'published_at' => now(),
            ]
        );
    }
}
