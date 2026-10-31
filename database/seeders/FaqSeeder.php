<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What are peptides?',
                'answer' => 'Peptides are short chains of amino acids that occur naturally within the body and are also studied extensively in scientific research. Different peptides are investigated for different biological functions and mechanisms.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'What are these products intended for?',
                'answer' => 'All products supplied by our store are for laboratory research use only. They are not medicinal products and are not designed for human consumption, diagnostic or therapeutic procedures.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'How should peptides be stored?',
                'answer' => 'Lyophilised peptides should be stored in cool, dry conditions, protected from light, and away from repeated temperature cycling. Once reconstituted, peptides should be stored under cool, protected conditions and used within short-term stability windows. Refer to our Peptide Storage &amp; Stability Guide for full details.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Do you provide product information?',
                'answer' => 'Yes. Each batch is supplied with a Certificate of Analysis detailing purity, identity and analytical data. Batch-specific information always takes precedence over general guidance.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'question' => 'Are peptides suitable for everyone?',
                'answer' => 'No. Our products are for laboratory research use only and are not for human consumption. They are not suitable for use outside of research-appropriate environments.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'question' => 'Do you provide advice on using peptides?',
                'answer' => 'No. We do not provide preparation, measurement or administration guidance under any circumstances. All handling, solvent selection and experimental procedures are determined by the receiving laboratory under its own validated protocols.',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'question' => 'Are your products tested?',
                'answer' => 'Yes. Every batch is produced using rigorous quality control processes and is supplied with a Certificate of Analysis detailing purity, identity and analytical data.',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'question' => 'How do I place an order?',
                'answer' => 'Browse our shop, add products to your cart, and proceed to checkout. Orders are processed once payment has been successfully received.',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'question' => 'How long does delivery take?',
                'answer' => 'Estimated delivery times are provided by the courier and are not guaranteed. Processing times may vary depending on order volume, product availability and verification checks.',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'question' => 'Do you ship across the UK?',
                'answer' => 'Yes, we offer delivery across the UK. Delivery options may vary depending on your location and courier availability.',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'question' => 'How can I contact us?',
                'answer' => 'Please email us at hello@example.com or call +1 555 000 0000. Please note we cannot accept or reply to text messages.',
                'sort_order' => 11,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'is_active' => $faq['is_active'],
                ]
            );
        }
    }
}
