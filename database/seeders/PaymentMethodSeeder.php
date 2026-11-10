<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'type' => 'bank',
                'label' => 'Commercial Bank of Ethiopia (CBE)',
                'bank_name' => 'Commercial Bank of Ethiopia',
                'account_name' => 'Invite Technologies PLC',
                'account_number' => '1000XXXXXXXXX',
                'phone_number' => null,
                'instructions_en' => 'Transfer via CBE Mobile Banking or in-branch. Include your payment code in the reason/remark field.',
                'instructions_am' => 'በCBE ሞባይል ባንኪንግ ወይም በቅርንጫፍ ይክፈሉ። በክፍያ ምክንያት (Reason) ቦታ ላይ የክፍያ ኮድዎን ያስገቡ።',
                'qr_image_path' => null,
                'is_active' => true,
                'sort' => 1,
            ],
            [
                'type' => 'mobile_money',
                'label' => 'Telebirr',
                'bank_name' => null,
                'account_name' => 'Invite Technologies',
                'account_number' => '09XXXXXXXX',
                'phone_number' => '09XXXXXXXX',
                'instructions_en' => 'Send money to Telebirr merchant/number. Add payment code to remark.',
                'instructions_am' => 'በቴሌብር ይክፈሉ። በክፍያ ምክንያት (Remark) ቦታ ላይ የክፍያ ኮድዎን ይጻፉ።',
                'qr_image_path' => null,
                'is_active' => true,
                'sort' => 2,
            ],
            [
                'type' => 'bank',
                'label' => 'Bank of Abyssinia',
                'bank_name' => 'Bank of Abyssinia',
                'account_name' => 'Invite Technologies PLC',
                'account_number' => '8888XXXXXXXXX',
                'phone_number' => null,
                'instructions_en' => 'Transfer via BoA Mobile App. Please include your payment code in the payment remark.',
                'instructions_am' => 'በአቢሲኒያ ባንክ ሞባይል መተግበሪያ ይክፈሉ። በክፍያ ምክንያት ቦታ ላይ የክፍያ ኮድዎን ያስገቡ።',
                'qr_image_path' => null,
                'is_active' => true,
                'sort' => 3,
            ],
        ];

        foreach ($methods as $data) {
            PaymentMethod::updateOrCreate(
                ['label' => $data['label']],
                $data
            );
        }
    }
}
