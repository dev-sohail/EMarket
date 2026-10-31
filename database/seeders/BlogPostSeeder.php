<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::first();

        $content = '<h2>The Grey Zone Between Research and Wellness</h2>
<p>A recent international study published in JAMA described peptides as existing in a "grey zone" — straddling medicine, wellness, and even illicit markets. Synthetic peptides such as BPC157, TB500, and MOTSC are increasingly promoted online for enhancement purposes, often without regulatory oversight. Researchers warn that this rapid mainstream adoption has outpaced safety frameworks, creating confusion for consumers and clinicians alike.</p>

<h2>Scientific Progress Behind the Headlines</h2>
<p>Despite the hype, legitimate peptide science is advancing rapidly. According to PeptideJournal, the past eighteen months have seen landmark developments:</p>
<ul>
<li>FDA approval of the first mitochondria-targeted drug</li>
<li>AI-designed antimicrobial peptides outperforming traditional antibiotics</li>
<li>Record-breaking results from triple-agonist peptides in obesity trials</li>
</ul>
<p>These breakthroughs highlight the real promise of peptides — when developed, tested, and regulated properly.</p>

<h2>Responsible Communication Matters</h2>
<p>At PoweredIn Peptides, we believe clarity and compliance are essential. Our materials are strictly for laboratory research use only, not for human consumption. By maintaining transparency and scientific integrity, we help laboratories explore peptide mechanisms responsibly — without contributing to misinformation or unsafe practices.</p>

<h2>Closing Thought</h2>
<p>Peptides are shaping the future of molecular medicine, but the conversation needs balance. Between viral trends and genuine breakthroughs lies a space for credible, ethical research — and that\'s where PoweredIn Peptides stands.</p>';

        $excerpt = 'Over the past two years, peptides have exploded across social media and wellness platforms. But as Stanford Medicine researchers recently noted, the hype is racing far ahead of the science.';

        BlogPost::updateOrCreate(
            ['slug' => 'the-peptide-hype'],
            [
                'title' => 'The Peptide Hype: Science, Speculation, and the Search for Cellular Innovation',
                'slug' => 'the-peptide-hype',
                'content' => $content,
                'excerpt' => $excerpt,
                'featured_image' => null,
                'status' => 'published',
                'show_on_site' => true,
                'is_featured' => false,
                'views' => 0,
                'author_id' => $author?->getKey(),
                'published_at' => now()->subDays(30),
                'meta_title' => 'The Peptide Hype: Science, Speculation, and Cellular Innovation',
                'meta_description' => 'Peptides have exploded across social media and wellness platforms, but the hype is racing far ahead of the science. A balanced look at research-grade peptides versus wellness trends.',
            ]
        );
    }
}
