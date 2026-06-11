const FIFA_CODES: Record<string, string> = {
    'Algeria': 'ALG', 'Argentina': 'ARG', 'Australia': 'AUS', 'Austria': 'AUT',
    'Belgium': 'BEL', 'Bosnia-Herzegovina': 'BIH', 'Brazil': 'BRA', 'Canada': 'CAN',
    'Cape Verde Islands': 'CPV', 'Colombia': 'COL', 'Congo DR': 'COD', 'Croatia': 'CRO',
    'Curaçao': 'CUW', 'Czechia': 'CZE', 'Ecuador': 'ECU', 'Egypt': 'EGY',
    'England': 'ENG', 'France': 'FRA', 'Germany': 'GER', 'Ghana': 'GHA',
    'Haiti': 'HAI', 'Iran': 'IRN', 'Iraq': 'IRQ', 'Ivory Coast': 'CIV',
    'Japan': 'JPN', 'Jordan': 'JOR', 'Mexico': 'MEX', 'Morocco': 'MAR',
    'Netherlands': 'NED', 'New Zealand': 'NZL', 'Norway': 'NOR', 'Panama': 'PAN',
    'Paraguay': 'PAR', 'Portugal': 'POR', 'Qatar': 'QAT', 'Saudi Arabia': 'KSA',
    'Scotland': 'SCO', 'Senegal': 'SEN', 'South Africa': 'RSA', 'South Korea': 'KOR',
    'Spain': 'ESP', 'Sweden': 'SWE', 'Switzerland': 'SUI', 'Tunisia': 'TUN',
    'Turkey': 'TUR', 'United States': 'USA', 'Uruguay': 'URU', 'Uzbekistan': 'UZB',
};

export function teamCode(name: string): string {
    return FIFA_CODES[name] ?? name.slice(0, 3).toUpperCase();
}
