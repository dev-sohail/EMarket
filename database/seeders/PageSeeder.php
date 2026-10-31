<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'title' => 'About Us',
                'sort_order' => 1,
                'status' => 'published',
                'meta_title' => 'About PoweredIn Peptides',
                'meta_description' => 'Learn about PoweredIn Peptides and our commitment to quality, transparency, and service.',
                'content' => '<h2>About PoweredIn Peptides</h2>
<p>At Poweredin Peptides, our aim is simple: to provide a professional, transparent and customer-focused experience for people interested in learning more about peptide products and research.</p>
<p>We believe that quality, consistency and clear information should be at the heart of everything we do. That\'s why we focus on sourcing products from established suppliers, presenting information clearly and maintaining high standards throughout our product range.</p>

<h2>Quality &amp; Transparency</h2>
<p>We understand that choosing a peptide supplier requires trust. Our approach is built around transparency, attention to detail and responsible communication.</p>
<p>Where applicable, product information and documentation are provided to help customers understand what they are purchasing. We avoid exaggerated claims and believe customers should have access to straightforward information rather than unrealistic promises.</p>

<h2>Customer Focused</h2>
<p>Whether you\'re discovering peptides for the first time or already familiar with the subject, we\'re committed to making your experience with Poweredin Peptides as straightforward as possible.</p>
<p>Our team is always working to improve our product selection, website and customer service so that every interaction is professional, clear and reliable.</p>

<h2>Our Commitment</h2>
<p>Poweredin Peptides is built around three core principles:</p>
<ul>
<li><strong>Quality</strong> — Taking care with the products and suppliers we work with.</li>
<li><strong>Transparency</strong> — Providing clear, honest and accessible product information.</li>
<li><strong>Service</strong> — Putting professionalism and customer experience first.</li>
</ul>
<p>We are committed to continually developing Poweredin Peptides while maintaining the standards and values on which the business was built.</p>

<h2>Poweredin Peptides</h2>
<p>Quality. Transparency. Confidence.</p>
<p>Products are supplied subject to applicable laws and regulations and should only be used in accordance with relevant professional, regulatory and manufacturer guidance.</p>

<h2>Why Choose Poweredin Peptides?</h2>
<p>At Poweredin Peptides, we believe choosing a peptide supplier should be about more than just price. It should be about quality, transparency, reliability and service.</p>
<ul>
<li><strong>Quality Focused</strong> — We take care when selecting the products and suppliers we work with, with a strong focus on consistency and quality standards.</li>
<li><strong>Transparency</strong> — We believe customers deserve clear and straightforward information. We aim to provide accurate product details and relevant documentation wherever available.</li>
<li><strong>Reliable Service</strong> — From placing your order to receiving your products, we strive to provide a smooth, professional and dependable customer experience.</li>
<li><strong>Carefully Selected Products</strong> — Our range is developed with careful consideration, giving customers access to a selection of peptide products from established supply channels.</li>
<li><strong>Customer First</strong> — We value every customer and are committed to responsive communication, helpful support and continually improving our service.</li>
<li><strong>Professional &amp; Responsible</strong> — We take our responsibilities seriously. We avoid exaggerated promises and focus on providing clear information so customers can make informed decisions.</li>
</ul>',
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms & Conditions',
                'sort_order' => 2,
                'status' => 'published',
                'meta_title' => 'Terms & Conditions',
                'meta_description' => 'The terms that apply when you purchase from PoweredIn Peptides.',
                'content' => '<h2>1. Introduction</h2>
<p>These Terms &amp; Conditions govern all purchases made through PoweredIn Peptides. By placing an order, you confirm that you have read, understood and agreed to these terms in full.</p>
<p>PoweredIn Peptides supplies research-grade peptide materials intended solely for laboratory research, scientific analysis and educational study. All products are sold for research use only and are not for human consumption, clinical use, therapeutic use or diagnostic procedures.</p>

<h2>2. Research Use Only</h2>
<p>All peptide products supplied by PoweredIn Peptides are:</p>
<ul>
<li>For laboratory research purposes only</li>
<li>Not intended for human or animal consumption</li>
<li>Not medicines, supplements or therapeutic products</li>
<li>Not to be used in vivo, in clinical settings or in any form of administration</li>
</ul>
<p>Customers are responsible for ensuring that all materials are handled in accordance with relevant professional, regulatory and manufacturer guidance.</p>

<h2>3. Eligibility to Purchase</h2>
<p>By purchasing from PoweredIn Peptides, you confirm that:</p>
<ul>
<li>You are over 18 years of age</li>
<li>You are purchasing on behalf of a laboratory, research organisation or for educational study</li>
<li>You understand the nature of research-grade materials</li>
<li>You will use all products responsibly and in accordance with applicable regulations</li>
</ul>

<h2>4. Product Information</h2>
<p>PoweredIn Peptides provides product information and documentation where available to support transparency and responsible communication.</p>
<p>We do not make exaggerated claims or promises regarding product performance.</p>
<p>All images, descriptions and specifications are provided for general informational purposes only.</p>

<h2>5. Ordering &amp; Payment</h2>
<p>Orders are processed once payment has been successfully received.</p>
<p>PoweredIn Peptides reserves the right to refuse or cancel any order where misuse, inappropriate intent or regulatory concerns are identified.</p>

<h2>6. Shipping &amp; Delivery</h2>
<p>Orders are dispatched to the address provided at checkout.</p>
<p>Customers are responsible for ensuring that delivery details are accurate and that materials can be received safely and appropriately.</p>

<h2>7. No Returns Policy</h2>
<p>Due to the nature of research-grade peptide materials, we do not accept returns under any circumstances.</p>
<p>This includes (but is not limited to):</p>
<ul>
<li>Change of mind</li>
<li>Incorrect ordering</li>
<li>Unwanted items</li>
<li>Misunderstanding of product purpose</li>
<li>Incompatibility with intended research</li>
</ul>
<p>All sales are final.</p>

<h2>8. Liability</h2>
<p>PoweredIn Peptides is not liable for:</p>
<ul>
<li>Misuse of products</li>
<li>Improper handling or storage</li>
<li>Use outside of research-appropriate environments</li>
<li>Any application involving human or animal consumption</li>
<li>Any form of administration, injection or ingestion</li>
</ul>
<p>Customers assume full responsibility for ensuring safe, compliant and appropriate use of all materials.</p>

<h2>9. Compliance &amp; Regulation</h2>
<p>Products are supplied subject to applicable laws and regulations.</p>
<p>Customers must ensure compliance with all relevant professional, regulatory and manufacturer guidance.</p>

<h2>10. Amendments</h2>
<p>PoweredIn Peptides may update these Terms &amp; Conditions at any time. Continued use of the website or purchase of products constitutes acceptance of any updated terms.</p>

<h2>11. Contact Information</h2>
<p>For any queries relating to these Terms &amp; Conditions, please contact our customer support team via the details provided on our website.</p>

<h2>Final Compliance Statement</h2>
<p>For research purposes only. Not for human consumption.</p>',
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'sort_order' => 3,
                'status' => 'published',
                'meta_title' => 'Privacy Policy',
                'meta_description' => 'How we collect, use, store and protect your personal information.',
                'content' => '<h2>1. Introduction</h2>
<p>This Privacy Policy explains how PoweredIn Peptides ("we", "us", "our") collects, uses, stores and protects personal information when you visit our website or make a purchase.</p>
<p>We are committed to maintaining transparency, clarity and responsible communication, consistent with the values outlined on our About Us page.</p>
<p>By using our website, you agree to the practices described in this policy.</p>

<h2>2. Information We Collect</h2>
<p>We may collect the following types of information:</p>
<p><strong>Information You Provide</strong></p>
<ul>
<li>Name</li>
<li>Email address</li>
<li>Billing and shipping details</li>
<li>Order information</li>
<li>Messages sent through our contact forms</li>
</ul>
<p><strong>Automatically Collected Information</strong></p>
<ul>
<li>IP address</li>
<li>Browser type</li>
<li>Device information</li>
<li>Pages visited</li>
<li>Basic analytics data</li>
</ul>
<p>This information helps us maintain a professional, reliable customer experience, consistent with our service principles.</p>

<h2>3. How We Use Your Information</h2>
<p>We use personal information to:</p>
<ul>
<li>Process and fulfil orders</li>
<li>Provide customer support</li>
<li>Improve our website and services</li>
<li>Communicate order updates</li>
<li>Maintain accurate records</li>
<li>Detect and prevent fraudulent activity</li>
</ul>
<p>We do not sell, rent or trade your personal information.</p>

<h2>4. Legal Basis for Processing</h2>
<p>We process personal data under the following lawful bases:</p>
<ul>
<li>Contractual necessity — to fulfil your order</li>
<li>Legitimate interests — to improve our services and maintain security</li>
<li>Consent — when you opt in to receive communications</li>
</ul>

<h2>5. Cookies &amp; Tracking Technologies</h2>
<p>Our website uses cookies to:</p>
<ul>
<li>Enable essential site functionality</li>
<li>Improve user experience</li>
<li>Analyse website performance</li>
</ul>
<p>You can manage cookie preferences through your browser settings.</p>

<h2>6. Sharing Your Information</h2>
<p>We may share information with:</p>
<ul>
<li>Payment processors</li>
<li>Delivery partners</li>
<li>Fraud-prevention services</li>
<li>Website hosting and analytics providers</li>
</ul>
<p>These partners only receive the information necessary to perform their services. We do not share information for marketing by third parties.</p>

<h2>7. Data Security</h2>
<p>We take care to protect personal information through:</p>
<ul>
<li>Secure payment processing</li>
<li>Encrypted website connections (HTTPS)</li>
<li>Controlled access to customer data</li>
</ul>
<p>Our commitment to responsible handling aligns with our broader principles of quality and transparency.</p>

<h2>8. Data Retention</h2>
<p>We retain personal information only as long as necessary to:</p>
<ul>
<li>Fulfil orders</li>
<li>Maintain legal and financial records</li>
<li>Support customer service</li>
</ul>
<p>After this period, data is securely deleted or anonymised.</p>

<h2>9. Your Rights</h2>
<p>Depending on your location, you may have rights to:</p>
<ul>
<li>Access your personal data</li>
<li>Request corrections</li>
<li>Request deletion</li>
<li>Restrict processing</li>
<li>Object to processing</li>
<li>Request data portability</li>
</ul>
<p>To exercise these rights, contact us through the details on our website.</p>

<h2>10. Children\'s Privacy</h2>
<p>Our website is not intended for individuals under 18. We do not knowingly collect information from minors.</p>

<h2>11. Research Use Only Notice</h2>
<p>All products sold by PoweredIn Peptides are for research use only and not for human consumption, as stated across our site.</p>
<p>This notice is included here to ensure clarity and responsible communication.</p>

<h2>12. Changes to This Policy</h2>
<p>We may update this Privacy Policy periodically. Any changes will be posted on this page with an updated revision date.</p>
<p>Continued use of the website indicates acceptance of the updated policy.</p>

<h2>13. Contact Information</h2>
<p>For questions about this Privacy Policy or your personal data, please contact our support team using the details provided on our website.</p>',
            ],
            [
                'slug' => 'delivery-info',
                'title' => 'Delivery & Information',
                'sort_order' => 4,
                'status' => 'published',
                'meta_title' => 'Shipping & Delivery Policy',
                'meta_description' => 'How orders are processed, dispatched and delivered when purchasing from PoweredIn Peptides.',
                'content' => '<h2>1. Introduction</h2>
<p>This Shipping &amp; Delivery Policy explains how orders are processed, dispatched and delivered when purchasing from PoweredIn Peptides. By placing an order, you agree to the terms outlined below.</p>
<p>All products supplied by PoweredIn Peptides are for research use only and not for human consumption.</p>

<h2>2. Order Processing</h2>
<p>Orders are processed once payment has been successfully received. Processing times may vary depending on order volume, product availability and verification checks.</p>
<p>Orders placed outside business hours, on weekends or public holidays will be processed on the next working day.</p>

<h2>3. Dispatch &amp; Delivery Options</h2>
<p>We offer a range of delivery options at checkout. Available services may include:</p>
<ul>
<li>Standard Delivery</li>
<li>Express / Next-Day Delivery</li>
<li>Tracked Delivery Services</li>
</ul>
<p>Delivery options may vary depending on your location and courier availability.</p>

<h2>4. Delivery Times</h2>
<p>Estimated delivery times are provided by the courier and are not guaranteed. External factors such as weather, courier delays or high-volume periods may affect delivery schedules.</p>
<p>PoweredIn Peptides is not responsible for delays caused by third-party couriers.</p>

<h2>5. Shipping Address Requirements</h2>
<p>Customers are responsible for ensuring that:</p>
<ul>
<li>The shipping address provided is accurate and complete</li>
<li>Someone is available to receive the delivery</li>
<li>The location is suitable for receiving research-grade materials</li>
</ul>
<p>We are not liable for orders delivered to incorrect or incomplete addresses provided by the customer.</p>

<h2>6. Tracking Information</h2>
<p>Where applicable, tracking details will be provided once the order has been dispatched.</p>
<p>Customers can monitor delivery progress directly through the courier\'s tracking system.</p>

<h2>7. Lost, Delayed or Damaged Parcels</h2>
<p>If a parcel is delayed, lost or arrives damaged, please contact us using the details on our website.</p>
<p>We will liaise with the courier to investigate the issue; however:</p>
<ul>
<li>Courier investigations may take several working days</li>
<li>Outcomes are determined by the courier\'s policies</li>
<li>Replacement or refund decisions are made at our discretion</li>
</ul>

<h2>8. No Returns Policy</h2>
<p>Due to the nature of research-grade peptide materials, we do not accept returns under any circumstances.</p>
<p>All sales are final.</p>
<p>This includes situations such as:</p>
<ul>
<li>Change of mind</li>
<li>Incorrect ordering</li>
<li>Unwanted items</li>
<li>Misunderstanding of product purpose</li>
<li>Incompatibility with intended research</li>
</ul>

<h2>9. International Shipping</h2>
<p>Where international shipping is available, customers are responsible for:</p>
<ul>
<li>Any customs duties, taxes or import fees</li>
<li>Ensuring compliance with local regulations</li>
<li>Providing accurate delivery information</li>
</ul>
<p>We are not responsible for parcels held, delayed or rejected by customs authorities.</p>

<h2>10. Research Use Only Notice</h2>
<p>All products supplied by PoweredIn Peptides are:</p>
<ul>
<li>For laboratory research use only</li>
<li>Not for human consumption</li>
<li>Not medicines, supplements or therapeutic products</li>
<li>Not intended for clinical, diagnostic or in vivo use</li>
</ul>
<p>This notice applies to all orders and deliveries.</p>

<h2>11. Contact Information</h2>
<p>For questions regarding shipping or delivery, please contact our support team using the details provided on our website.</p>',
            ],
            [
                'slug' => 'return-policy',
                'title' => 'Return Policy',
                'sort_order' => 5,
                'status' => 'published',
                'meta_title' => 'Return Policy',
                'meta_description' => 'Our no-returns policy for research-grade peptide materials.',
                'content' => '<h2>1. Introduction</h2>
<p>This Returns Policy outlines the conditions under which PoweredIn Peptides accepts or declines returns. Due to the nature of research-grade peptide materials, strict handling rules apply.</p>
<p>All products supplied by PoweredIn Peptides are for research use only and not for human consumption.</p>

<h2>2. No Returns Policy</h2>
<p>PoweredIn Peptides operates a strict no-returns policy. We do not accept returns under any circumstances.</p>
<p>This includes, but is not limited to:</p>
<ul>
<li>Change of mind</li>
<li>Incorrect ordering</li>
<li>Unwanted items</li>
<li>Misunderstanding of product purpose</li>
<li>Incompatibility with intended research</li>
<li>Perceived differences in appearance, colour or packaging</li>
<li>Delivery delays caused by couriers or external factors</li>
</ul>
<p>All sales are final.</p>

<h2>3. Product Integrity</h2>
<p>Research-grade peptide materials cannot be returned because:</p>
<ul>
<li>They require controlled handling</li>
<li>They cannot be re-stocked or re-issued once dispatched</li>
<li>Their stability and integrity cannot be guaranteed once they leave our facility</li>
<li>They may be exposed to conditions outside our control</li>
</ul>
<p>For these reasons, returns are not permitted under any circumstances.</p>

<h2>4. Damaged or Missing Items</h2>
<p>If an item arrives damaged or appears to be missing, please contact us immediately.</p>
<p>We will:</p>
<ul>
<li>Investigate the issue with the courier</li>
<li>Request supporting information (e.g., photos of packaging)</li>
<li>Review the case in line with courier policies</li>
</ul>
<p>Please note:</p>
<ul>
<li>Courier investigations may take several working days</li>
<li>Outcomes are determined by the courier</li>
<li>Replacement decisions are made at our discretion</li>
</ul>
<p>This process does not constitute a return.</p>

<h2>5. Incorrect Address or Delivery Issues</h2>
<p>We are not responsible for:</p>
<ul>
<li>Orders delivered to incorrect or incomplete addresses provided by the customer</li>
<li>Parcels that cannot be delivered due to access restrictions</li>
<li>Parcels returned to courier depots due to missed delivery attempts</li>
</ul>
<p>These situations do not qualify for returns or refunds.</p>

<h2>6. Research Use Only Notice</h2>
<p>All products supplied by PoweredIn Peptides are:</p>
<ul>
<li>For laboratory research use only</li>
<li>Not for human consumption</li>
<li>Not medicines, supplements or therapeutic products</li>
<li>Not intended for clinical, diagnostic or in vivo use</li>
</ul>
<p>This applies to all orders and all delivery outcomes.</p>

<h2>7. Contact Information</h2>
<p>If you have questions regarding this Returns Policy, please contact our support team using the details provided on our website.</p>',
            ],
            [
                'slug' => 'peptide-storage',
                'title' => 'Peptide Storage & Stability Guide',
                'sort_order' => 6,
                'status' => 'published',
                'meta_title' => 'Peptide Storage & Stability Guide',
                'meta_description' => 'A laboratory handling guide for storing lyophilised and reconstituted research peptides.',
                'content' => '<h2>Peptide Storage and Stability: A Laboratory Handling Guide</h2>
<p>Lyophilised research peptides are stable materials when stored correctly, and their stability is influenced by temperature, light, moisture and time. This guide outlines the general factors that govern peptide stability in laboratory environments.</p>
<p><strong>PoweredIn Peptides does not provide preparation, measurement or administration guidance under any circumstances.</strong> All handling, solvent selection and experimental procedures are determined by the receiving laboratory under its own validated protocols. Solvent selection, handling and all experimental procedures are determined by the receiving laboratory.</p>

<h2>Storing Lyophilised Peptides (Before Reconstitution)</h2>
<ul>
<li>Lyophilised (freeze-dried) peptides are inherently stable because the dry state protects them from moisture and light.</li>
<li>General laboratory practice typically includes:</li>
<ul>
<li>Cool, dry storage to maintain long-term stability.</li>
<li>Protection from light, especially for sequences containing photosensitive residues such as tryptophan, tyrosine, phenylalanine, cysteine or methionine.</li>
<li>Avoiding repeated temperature cycling, as condensation can shorten the usable life of hygroscopic materials.</li>
</ul>
<li>Lyophilised peptides degrade gradually over time, with changes in structure and charge distribution occurring as stability decreases.</li>
</ul>

<h2>Storing Peptides After Reconstitution (In Solution)</h2>
<p>Peptides in solution are significantly less stable than in their sealed lyophilised form.</p>
<p>General laboratory considerations include:</p>
<ul>
<li>Short-term storage only, as stability windows in solution are much shorter than in lyophilised form.</li>
<li>Cool, protected conditions, as temperature remains the largest factor affecting degradation.</li>
<li>Awareness of sequence-specific sensitivity, since peptides containing asparagine, glutamine, cysteine or methionine may be inherently less stable.</li>
</ul>

<h2>Stability Factors</h2>
<p>Key influences on peptide stability include:</p>
<ul>
<li><strong>Temperature</strong> — lower temperatures slow hydrolysis and oxidation.</li>
<li><strong>Moisture</strong> — lyophilised peptides are hygroscopic and sensitive to condensation.</li>
<li><strong>Light</strong> — certain amino acids are photosensitive.</li>
<li><strong>Sequence composition</strong> — some residues reduce inherent stability.</li>
<li><strong>Time in solution</strong> — stability decreases significantly once reconstituted.</li>
</ul>

<h2>Certificates of Analysis</h2>
<p>Each batch is supplied with a Certificate of Analysis detailing purity, identity and analytical data. Batch-specific information always takes precedence over general guidance.</p>

<h2>Research Use Only</h2>
<p>All materials supplied by PoweredIn Peptides are for laboratory research use only. They are not medicinal products and are not designed for human consumption, diagnostic or therapeutic procedures. We do not provide preparation, measurement or administration guidance under any circumstances, and enquiries indicating interest in human use will not be assisted.</p>',
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'status' => $page['status'],
                    'meta_title' => $page['meta_title'],
                    'meta_description' => $page['meta_description'],
                    'sort_order' => $page['sort_order'],
                ]
            );
        }
    }
}
