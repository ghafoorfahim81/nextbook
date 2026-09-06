import overview from './overview'
import sales from './sales'
import purchases from './purchases'
import inventory from './inventory'
import accounting from './accounting'
import cash from './cash'
import expenses from './expenses'
import hr from './hr'
import reports from './reports'
import administration from './administration'

export default {
    meta: {
        badge: 'NextBook — Financial & Administrative System',
        title: 'User Manual',
        subtitle: 'Module-by-module guides for users',
        version: 'Version 2.0 · September 2026',
        howToRead:
            'Pick a module from the list below to open its full guide. If you are new to the system, start with “Getting started & overview”. Each guide has its own table of contents and can be read on its own.',
    },
    guides: [overview, sales, purchases, inventory, accounting, cash, expenses, hr, reports, administration],
}
