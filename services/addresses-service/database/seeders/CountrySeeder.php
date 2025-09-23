<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => 'United States',
                'code' => 'US',
                'iso3' => 'USA',
                'phone_code' => '+1',
                'regions' => [
                    ['name' => 'Alabama', 'code' => 'AL'],
                    ['name' => 'California', 'code' => 'CA'],
                    ['name' => 'Florida', 'code' => 'FL'],
                    ['name' => 'New York', 'code' => 'NY'],
                    ['name' => 'Texas', 'code' => 'TX'],
                ]
            ],
            [
                'name' => 'Canada',
                'code' => 'CA',
                'iso3' => 'CAN',
                'phone_code' => '+1',
                'regions' => [
                    ['name' => 'Ontario', 'code' => 'ON'],
                    ['name' => 'Quebec', 'code' => 'QC'],
                    ['name' => 'British Columbia', 'code' => 'BC'],
                    ['name' => 'Alberta', 'code' => 'AB'],
                ]
            ],
            [
                'name' => 'France',
                'code' => 'FR',
                'iso3' => 'FRA',
                'phone_code' => '+33',
                'regions' => [
                    ['name' => 'Île-de-France', 'code' => 'IDF'],
                    ['name' => 'Provence-Alpes-Côte d\'Azur', 'code' => 'PACA'],
                    ['name' => 'Auvergne-Rhône-Alpes', 'code' => 'ARA'],
                    ['name' => 'Nouvelle-Aquitaine', 'code' => 'NAQ'],
                ]
            ],
            [
                'name' => 'Germany',
                'code' => 'DE',
                'iso3' => 'DEU',
                'phone_code' => '+49',
                'regions' => [
                    ['name' => 'Bavaria', 'code' => 'BY'],
                    ['name' => 'North Rhine-Westphalia', 'code' => 'NW'],
                    ['name' => 'Baden-Württemberg', 'code' => 'BW'],
                    ['name' => 'Berlin', 'code' => 'BE'],
                ]
            ],
            [
                'name' => 'United Kingdom',
                'code' => 'GB',
                'iso3' => 'GBR',
                'phone_code' => '+44',
                'regions' => [
                    ['name' => 'England', 'code' => 'ENG'],
                    ['name' => 'Scotland', 'code' => 'SCT'],
                    ['name' => 'Wales', 'code' => 'WLS'],
                    ['name' => 'Northern Ireland', 'code' => 'NIR'],
                ]
            ],
            [
                'name' => 'Australia',
                'code' => 'AU',
                'iso3' => 'AUS',
                'phone_code' => '+61',
                'regions' => [
                    ['name' => 'New South Wales', 'code' => 'NSW'],
                    ['name' => 'Victoria', 'code' => 'VIC'],
                    ['name' => 'Queensland', 'code' => 'QLD'],
                    ['name' => 'Western Australia', 'code' => 'WA'],
                ]
            ]
        ];

        foreach ($countries as $countryData) {
            $regions = $countryData['regions'];
            unset($countryData['regions']);

            $country = Country::firstOrCreate(['code' => $countryData['code']], $countryData);

            foreach ($regions as $regionData) {
                $regionData['country_id'] = $country->id;
                Region::firstOrCreate(['code' => $regionData['code'], 'country_id' => $country->id], $regionData);
            }
        }
    }
}