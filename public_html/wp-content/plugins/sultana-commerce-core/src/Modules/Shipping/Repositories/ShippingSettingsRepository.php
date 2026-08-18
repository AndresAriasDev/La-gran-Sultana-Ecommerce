<?php

namespace Sultana\CommerceCore\Modules\Shipping\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingSettingsRepository
{
    public const RATES_OPTION          = 'scc_shipping_cargotrans_rates';
    public const MUNICIPALITIES_OPTION = 'scc_shipping_cargotrans_municipalities';
    public const DEPARTMENTS_OPTION    = 'scc_shipping_nicaragua_departments';
    public const EXPRESS_OPTION        = 'scc_shipping_express_granada_settings';
    public const STORE_PICKUP_OPTION   = 'scc_shipping_store_pickup_settings';
    public const DATA_VERSION_OPTION   = 'scc_shipping_data_version';
    private const DATA_VERSION         = '2026-10-cargotrans-official-address-v2';

    public function ensure_defaults(): void
    {
        if ( self::DATA_VERSION !== get_option( self::DATA_VERSION_OPTION ) ) {
            update_option( self::RATES_OPTION, self::default_cargotrans_rates(), false );
            update_option( self::MUNICIPALITIES_OPTION, self::default_cargotrans_municipalities(), false );
            update_option( self::DEPARTMENTS_OPTION, self::default_nicaragua_departments(), false );
            update_option( self::DATA_VERSION_OPTION, self::DATA_VERSION, false );
        }

        $express_settings = get_option( self::EXPRESS_OPTION, false );

        if ( false === $express_settings || ! is_array( $express_settings ) ) {
            add_option( self::EXPRESS_OPTION, self::default_express_granada_settings(), '', false );
        } else {
            update_option(
                self::EXPRESS_OPTION,
                array_replace_recursive( self::default_express_granada_settings(), $express_settings ),
                false
            );
        }

        $store_pickup_settings = get_option( self::STORE_PICKUP_OPTION, false );

        if ( false === $store_pickup_settings || ! is_array( $store_pickup_settings ) ) {
            add_option( self::STORE_PICKUP_OPTION, self::default_store_pickup_settings(), '', false );
        } else {
            update_option(
                self::STORE_PICKUP_OPTION,
                array_replace_recursive( self::default_store_pickup_settings(), $store_pickup_settings ),
                false
            );
        }
    }

    public function cargotrans_rates(): array
    {
        $rates = get_option( self::RATES_OPTION, [] );

        return is_array( $rates ) ? $rates : [];
    }

    public function cargotrans_municipalities(): array
    {
        $municipalities = get_option( self::MUNICIPALITIES_OPTION, [] );

        return is_array( $municipalities ) ? $municipalities : [];
    }

    public function express_granada_settings(): array
    {
        $settings = get_option( self::EXPRESS_OPTION, [] );

        return is_array( $settings ) ? $settings : [];
    }

    public function store_pickup_settings(): array
    {
        $settings = get_option( self::STORE_PICKUP_OPTION, [] );

        return is_array( $settings ) ? $settings : [];
    }

    public function nicaragua_departments(): array
    {
        $departments = get_option( self::DEPARTMENTS_OPTION, [] );

        return is_array( $departments ) ? $departments : [];
    }

    public static function normalize_location_key( string $value ): string
    {
        $value = remove_accents( $value );
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9]+/', '-', $value );

        return trim( (string) $value, '-' );
    }

    public static function default_cargotrans_rates(): array
    {
        return [
            'ruta_primaria_a' => [
                'label'      => 'Ruta Primaria A',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 5, 'cost' => 122 ],
                    [ 'min' => 5.01, 'max' => 10, 'cost' => 194 ],
                    [ 'min' => 10.01, 'max' => 15, 'cost' => 240 ],
                    [ 'min' => 15.01, 'max' => 20, 'cost' => 270 ],
                    [ 'min' => 20.01, 'max' => 25, 'cost' => 329 ],
                    [ 'min' => 25.01, 'max' => 30, 'cost' => 453 ],
                ],
                'extra_kg'   => 21,
                'max_weight' => 30,
            ],
            'ruta_primaria_b' => [
                'label'      => 'Ruta Primaria B',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 5, 'cost' => 145 ],
                    [ 'min' => 5.01, 'max' => 10, 'cost' => 235 ],
                    [ 'min' => 10.01, 'max' => 15, 'cost' => 292 ],
                    [ 'min' => 15.01, 'max' => 20, 'cost' => 330 ],
                    [ 'min' => 20.01, 'max' => 25, 'cost' => 402 ],
                    [ 'min' => 25.01, 'max' => 30, 'cost' => 557 ],
                ],
                'extra_kg'   => 21,
                'max_weight' => 30,
            ],
            'ruta_secundaria' => [
                'label'      => 'Ruta Secundaria',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 5, 'cost' => 279 ],
                    [ 'min' => 5.01, 'max' => 10, 'cost' => 345 ],
                    [ 'min' => 10.01, 'max' => 15, 'cost' => 395 ],
                    [ 'min' => 15.01, 'max' => 20, 'cost' => 507 ],
                    [ 'min' => 20.01, 'max' => 25, 'cost' => 579 ],
                ],
                'extra_kg'   => 42,
                'max_weight' => 25,
            ],
            'ruta_terciaria' => [
                'label'      => 'Ruta Terciaria',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 5, 'cost' => 337 ],
                    [ 'min' => 5.01, 'max' => 10, 'cost' => 385 ],
                    [ 'min' => 10.01, 'max' => 15, 'cost' => 436 ],
                    [ 'min' => 15.01, 'max' => 20, 'cost' => 547 ],
                    [ 'min' => 20.01, 'max' => 25, 'cost' => 619 ],
                ],
                'extra_kg'   => 42,
                'max_weight' => 25,
            ],
            'ruta_especial_a' => [
                'label'      => 'Ruta Especial A',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 1, 'cost' => 273 ],
                    [ 'min' => 1.01, 'max' => 5, 'cost' => 389 ],
                    [ 'min' => 5.01, 'max' => 10, 'cost' => 610 ],
                ],
                'extra_kg'   => 73,
                'max_weight' => 10,
            ],
            'ruta_especial_b' => [
                'label'      => 'Ruta Especial B',
                'brackets'   => [
                    [ 'min' => 0.01, 'max' => 50, 'cost' => 683 ],
                ],
                'extra_kg'   => 99,
                'max_weight' => 50,
            ],
        ];
    }

    public static function default_cargotrans_municipalities(): array
    {
        $groups = [
            'ruta_primaria_a' => [
                'Niquinohomo', 'El Raizón km 15-22', 'Nindirí', 'Masaya', 'Las Esquinas',
                'Diriamba', 'Nandasmo', 'Dolores', 'Catarina', 'Jinotepe', 'La Concepción',
                'San Marcos', 'Diriá', 'Diriomo', 'El Rosario', 'La Paz Carazo',
                'Granada', 'Masatepe', 'Nandaime', 'Rivas', 'Santa Teresa',
                'San Juan de Oriente', 'Managua Municipio',
            ],
            'ruta_primaria_b' => [
                'Tisma', 'El Guanacaste', 'Potosí', 'Popoyuapa', 'Buenos Aires', 'Tola',
                'San Juan del Sur', 'La Curva - Niquinohomo', 'Belén',
                'Pueblo Nuevo Rivas', 'San Jorge', 'Estelí', 'Totogalpa', 'Somoto',
                'Condega', 'Ocotal', 'San Benito', 'Las Canoas', 'Las Banderas',
                'Tecolostote', 'Boaco', 'San Patricio', 'Camoapa', 'Juigalpa',
                'Santo Tomás', 'Teustepe', 'Empalme Boaco', 'Empalme San Benito',
                'Telica', 'Quezalguaque', 'Nagarote', 'La Paz Centro', 'San Jacinto',
                'Posoltega', 'Chichigalpa', 'León', 'Chinandega', 'El Viejo', 'Corinto',
                'Las Maderas', 'Ciudad Darío', 'Chagüitillo', 'San Isidro',
                'La Trinidad', 'Sébaco', 'Matagalpa', 'Jinotega', 'Santa Cruz',
                'Nejapa', 'Veracruz', 'Ciudad Sandino', 'Ticuantepe', 'Los Brasiles',
                'El Crucero', 'Tipitapa', 'Mateare', 'Ciudad El Doral',
            ],
            'ruta_secundaria' => [
                'La Virgen', 'Ochomogo', 'Pueblo Nuevo - Estelí', 'Walagüina',
                'Palacagüina', 'San Lucas', 'Mozonte', 'La Palma', 'La Libertad',
                'Acoyapa', 'Santo Domingo', 'Villa Sandino', 'San Pedro de Lóvago',
                'Muhan', 'San Lorenzo', 'La Gateada', 'El Coral', 'La Batea',
                'Nueva Guinea', 'Muelle de Bueyes', 'El Rama', 'Precillas',
                'Malpaisillo', 'Mina El Limón', 'El Realejo', 'Ingenio San Antonio',
                'Paso Caballos', 'El Sauce', 'Villa Nueva', 'Somotillo',
                'Las Calabazas', 'Muy Muy', 'San Ramón', 'Matiguás',
                'San Rafael del Norte', 'El Tuma', 'La Concordia', 'Río Blanco',
                'La Dalia', 'Los Cedros', 'Villa El Carmen', 'San Rafael del Sur',
                'Montelimar', 'Masachapa', 'Pochomil',
            ],
            'ruta_terciaria' => [
                'La Conquista', 'Moyogalpa', 'Altagracia', 'San Juan de Limay',
                'San José de Cusmapa', 'San Nicolás', 'Santa Clara', 'Telpaneca',
                'Las Sabanas', 'San Juan de Río Coco', 'Wiwilí', 'San Fernando',
                'Susucayán', 'Jalapa', 'Quilalí', 'El Jícaro', 'Santa Lucía',
                'Comalapa', 'Cuapa', 'San José de los Remates', 'El Ayote',
                'La Esperanza', 'San Miguelito', 'El Almendro', 'Wapí',
                'Laurel Galán', 'Puerto La Esperanza', 'El Triunfo', 'El Tule',
                'Santo Domingo', 'La Aduana', 'Apompua - Juigalpa', 'San Carlos',
                'Las Azucenas', 'Colonia Rama', 'Los Chiles', 'Monte Rosa',
                'Empalme de Izapa', 'La Reynaga', 'El Tamarindo', 'Los Zarzales',
                'Momotombo', 'Ojo de Agua - Las Pilas', 'Villa 15 de Julio',
                'Ranchería', 'Tonalá', 'Jicaral', 'Cinco Pinos', 'Terrabona',
                'San Dionisio', 'Sébastian de Yalí', 'Rancho Grande', 'Esquipulas',
                'Waslala', 'El Cuá', 'San José de Bocay', 'Mulukukú', 'Paiwas',
                'Naranjo', 'Pantasma',
            ],
            'ruta_especial_a' => [
                'Bilwi-Puerto Cabezas', 'Siuna', 'Rosita', 'Bonanza', 'Sunsun',
                'Sahsa', 'El Naranjal-Puerto Cabezas', 'Banacruz',
                'Empalme Alamikamba', 'Bluefields',
            ],
            'ruta_especial_b' => [
                'Peñas Blancas', 'Sapoá', 'Playa Limón 1-2', 'Cárdenas - Rivas',
                'Las Salinas de Tola', 'Rancho Sta. Ana - Tola', 'Hacienda Iguana',
                'Las Manos', 'Puerto Príncipe', 'Achupa', 'Poneloya',
                'Santa Rosa del Peñón', 'Guasaule', 'Puerto Morazán',
                'Puerto Sandino', 'Las Peñitas', 'Puerto Viejo - Waslala',
                'Wanawana', 'Plan de Grama', 'Ayapal', 'Santa Rita',
                'San Pedro del Norte - Río Blanco', 'El Guineo', 'Coperno',
                'Waspán Atlántico', 'Corn Island', 'Laguna de Perlas',
                'Kukra Hill', 'El Tortuguero', 'Malacatoya',
            ],
        ];

        $municipalities = [];

        foreach ( $groups as $route => $municipality_names ) {
            foreach ( $municipality_names as $name ) {
                $municipalities[ self::normalize_location_key( $name ) ] = [
                    'label' => $name,
                    'route' => $route,
                ];
            }
        }

        $aliases = [
            'managua' => 'managua-municipio',
            'pueblo-nuevo' => 'pueblo-nuevo-esteli',
            'muelles-de-bueyes' => 'muelle-de-bueyes',
            'sebastian-de-yali' => 'sebastian-de-yali',
            'bilwi' => 'bilwi-puerto-cabezas',
            'puerto-cabezas' => 'bilwi-puerto-cabezas',
            'cardenas' => 'cardenas-rivas',
            'san-pedro-del-norte' => 'san-pedro-del-norte-rio-blanco',
        ];

        foreach ( $aliases as $alias => $target ) {
            if ( isset( $municipalities[ $target ] ) ) {
                $municipalities[ $alias ] = $municipalities[ $target ];
            }
        }

        return $municipalities;
    }

    public static function default_nicaragua_departments(): array
    {
        return [
            'boaco' => [
                'label' => 'Boaco',
                'municipalities' => [ 'San Lorenzo', 'San José de los Remates', 'Santa Lucía', 'Teustepe', 'Boaco', 'Camoapa' ],
            ],
            'carazo' => [
                'label' => 'Carazo',
                'municipalities' => [ 'Jinotepe', 'Dolores', 'Diriamba', 'San Marcos', 'El Rosario', 'La Paz Carazo' ],
            ],
            'chinandega' => [
                'label' => 'Chinandega',
                'municipalities' => [ 'Chinandega', 'El Viejo', 'Corinto', 'Chichigalpa', 'Posoltega', 'Puerto Morazán', 'Somotillo', 'Villa Nueva', 'Cinco Pinos' ],
            ],
            'chontales' => [
                'label' => 'Chontales',
                'municipalities' => [ 'Juigalpa', 'Acoyapa', 'Santo Tomás', 'Villa Sandino', 'San Pedro de Lóvago', 'La Libertad', 'Santo Domingo', 'El Coral', 'Comalapa', 'Cuapa' ],
            ],
            'esteli' => [
                'label' => 'Estelí',
                'municipalities' => [ 'Estelí', 'Pueblo Nuevo - Estelí', 'La Trinidad', 'San Nicolás', 'San Juan de Limay', 'Condega' ],
            ],
            'granada' => [
                'label' => 'Granada',
                'municipalities' => [ 'Granada', 'Diriá', 'Diriomo', 'Nandaime' ],
            ],
            'jinotega' => [
                'label' => 'Jinotega',
                'municipalities' => [ 'Jinotega', 'San Rafael del Norte', 'La Concordia', 'San José de Bocay', 'El Cuá', 'Pantasma', 'Wiwilí' ],
            ],
            'leon' => [
                'label' => 'León',
                'municipalities' => [ 'León', 'Telica', 'Quezalguaque', 'Nagarote', 'La Paz Centro', 'El Sauce', 'Achuapa', 'Santa Rosa del Peñón', 'Malpaisillo', 'Mina El Limón', 'Poneloya', 'Las Peñitas' ],
            ],
            'madriz' => [
                'label' => 'Madriz',
                'municipalities' => [ 'Somoto', 'San Lucas', 'Totogalpa', 'Palacagüina', 'Telpaneca', 'San Juan de Río Coco', 'Las Sabanas', 'San José de Cusmapa' ],
            ],
            'managua' => [
                'label' => 'Managua',
                'municipalities' => [ 'Managua Municipio', 'Ciudad Sandino', 'Mateare', 'Tipitapa', 'Veracruz', 'Ticuantepe', 'El Crucero', 'Los Brasiles', 'Ciudad El Doral', 'Los Cedros', 'Villa El Carmen', 'San Rafael del Sur', 'Montelimar', 'Masachapa', 'Pochomil', 'Puerto Sandino' ],
            ],
            'masaya' => [
                'label' => 'Masaya',
                'municipalities' => [ 'Masaya', 'Nindirí', 'Niquinohomo', 'La Concepción', 'Masatepe', 'Nandasmo', 'Catarina', 'San Juan de Oriente', 'El Raizón km 15-22', 'Las Esquinas', 'La Curva - Niquinohomo' ],
            ],
            'matagalpa' => [
                'label' => 'Matagalpa',
                'municipalities' => [ 'Matagalpa', 'Sébaco', 'Darío', 'San Isidro', 'Terrabona', 'San Dionisio', 'Muy Muy', 'San Ramón', 'Matiguás', 'Río Blanco', 'La Dalia', 'Rancho Grande', 'Waslala', 'El Tuma' ],
            ],
            'nueva-segovia' => [
                'label' => 'Nueva Segovia',
                'municipalities' => [ 'Ocotal', 'Mozonte', 'San Fernando', 'Santa Clara', 'Quilalí', 'El Jícaro', 'Jalapa', 'Susucayán', 'Las Manos' ],
            ],
            'raan' => [
                'label' => 'RAAN',
                'municipalities' => [ 'Bilwi-Puerto Cabezas', 'Siuna', 'Rosita', 'Bonanza', 'Sunsun', 'Sahsa', 'El Naranjal-Puerto Cabezas', 'Banacruz', 'Empalme Alamikamba', 'Waspán Atlántico', 'Mulukukú', 'Paiwas', 'Naranjo', 'Puerto Príncipe', 'Puerto Viejo - Waslala', 'Wanawana', 'Plan de Grama', 'Ayapal', 'Santa Rita', 'El Guineo', 'Coperno' ],
            ],
            'raas' => [
                'label' => 'RAAS',
                'municipalities' => [ 'Bluefields', 'Corn Island', 'Laguna de Perlas', 'Kukra Hill', 'El Tortuguero', 'Nueva Guinea', 'Muelle de Bueyes', 'El Rama', 'Wapí', 'La Esperanza', 'El Ayote', 'La Gateada', 'Muhan', 'San Pedro del Norte - Río Blanco', 'Las Azucenas', 'Colonia Rama', 'Los Chiles' ],
            ],
            'rio-san-juan' => [
                'label' => 'Río San Juan',
                'municipalities' => [ 'San Carlos', 'San Miguelito', 'El Almendro', 'Los Chiles' ],
            ],
            'rivas' => [
                'label' => 'Rivas',
                'municipalities' => [ 'Rivas', 'San Jorge', 'Belén', 'Potosí', 'Buenos Aires', 'Tola', 'San Juan del Sur', 'Sapoá', 'Cárdenas - Rivas', 'Moyogalpa', 'Altagracia', 'Las Salinas de Tola', 'Rancho Sta. Ana - Tola', 'Hacienda Iguana', 'La Virgen', 'Ochomogo' ],
            ],
        ];
    }

    public static function default_express_granada_settings(): array
    {
        return [
            'enabled'      => true,
            'cost'         => 50,
            'max_weight'   => 5,
            'municipality' => 'granada',
            'timezone'     => 'America/Managua',
            'schedule'     => [
                1 => [ [ 'from' => '08:00', 'to' => '17:00' ] ],
                2 => [ [ 'from' => '08:00', 'to' => '17:00' ] ],
                3 => [ [ 'from' => '08:00', 'to' => '17:00' ] ],
                4 => [ [ 'from' => '08:00', 'to' => '17:00' ] ],
                5 => [ [ 'from' => '08:00', 'to' => '17:00' ] ],
                6 => [ [ 'from' => '08:00', 'to' => '13:00' ] ],
            ],
        ];
    }

    public static function default_store_pickup_settings(): array
    {
        return [
            'branch_name' => 'Granada',
        ];
    }
}
