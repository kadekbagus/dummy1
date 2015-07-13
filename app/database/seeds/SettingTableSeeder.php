<?php
/**
 * Seeder for Setting
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SettingTableSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Seeding settings table...');

        try {
            DB::table('settings')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        Setting::unguard();

        $retailer_id = $this->getIdOfRetailerWithOmid('ORBIT-RETAILER-01');

        $record = [
            'setting_name'  => 'current_retailer',
            'setting_value' => $retailer_id,
            'status'        => 'active'
        ];
        Setting::create($record);
        $this->command->info(sprintf('    Create record `current_retailer` set to %s.', $retailer_id));
        $this->command->info('settings table seeded.');
    }

    private function getIdOfRetailerWithOmid($omid)
    {
        return Retailer::where('omid', '=', $omid)->firstOrFail()->merchant_id;
    }


}
