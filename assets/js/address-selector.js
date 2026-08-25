/**
 * Alertara QC - Structured Address Selector Component
 * Synchronized with EMERGENCY-COM standard:
 * - District (1 to 6)
 * - Barangay (Quezon City 142 Barangays filtered by District)
 * - House / Unit / Building No.
 * - Street Name
 * - Auto-combined formatted address
 */

const QC_BARANGAYS_BY_DISTRICT = {
    '1': [
        'Vasra', 'Bagong Pag-asa', 'Sto. Cristo', 'Project 6', 'Ramon Magsaysay', 'Alicia',
        'Bahay Toro', 'Katipunan', 'San Antonio', 'Veterans Village', 'Bungad', 'Phil-Am',
        'West Triangle', 'Sta. Cruz', 'Nayong Kanluran', 'Paltok', 'Paraiso', 'Mariblo',
        'Damayan', 'Del Monte', 'Masambong', 'Talayan', 'Sto. Domingo', 'Siena',
        'St. Peter', 'San Jose', 'Manresa', 'Damar', 'Pag-ibig sa Nayon', 'Balingasa',
        'Sta. Teresita', 'San Isidro Labrador', 'Paang Bundok', 'Salvacion', 'N.S Amoranto',
        'Maharlikha', 'Lourdes'
    ],
    '2': [
        'Bagong Silangan', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'
    ],
    '3': [
        'Silangan', 'Socorro', 'E. Rodriguez', 'West Kamias', 'East Kamias', 'Quirino 2-A',
        'Quirino 2-B', 'Quirino 2-C', 'Quirino 3-A', 'Claro (Quirino 3-B)', 'Duyan-Duyan',
        'Amihan', 'Matandang Balara', 'Pansol', 'Loyola Heights', 'San Roque', 'Mangga',
        'Masagana', 'Villa Maria Clara', 'Bayanihan', 'Camp Aguinaldo', 'White Plains',
        'Libis', 'Ugong Norte', 'Bagumbayan', 'Blue Ridge A', 'Blue Ridge B', 'St. Ignatius',
        'Milagrosa', 'Escopa I', 'Escopa II', 'Escopa III', 'Escopa IV', 'Marilag',
        'Bagumbuhay', 'Tagumpay', 'Dioquino Zobel'
    ],
    '4': [
        'Sacred Heart', 'Laging Handa', 'Obrero', 'Paligsahan', 'Roxas', 'Kamuning',
        'South Triangle', 'Pinagkaisahan', 'Immaculate Concepcion', 'San Martin De Porres',
        'Kaunlaran', 'Bagong Lipunan ng Crame', 'Horseshoe', 'Valencia', 'Tatalon',
        'Kalusugan', 'Kristong Hari', 'Damayang Lagi', 'Mariana', 'Doña Imelda', 'Santol',
        'Sto. Niño', 'San Isidro Galas', 'Doña Aurora', 'Don Manuel', 'Doña Josefa',
        'UP Village', 'Old Capitol Site', 'UP Campus', 'San Vicente', 'Teachers Village East',
        'Teachers Village West', 'Central', 'Pinyahan', 'Malaya', 'Sikatuna Village', 'Botocan',
        'Krus Na Ligas'
    ],
    '5': [
        'Bagbag', 'Capri', 'Greater Lagro', 'Gulod', 'Kaligayahan', 'Nagkaisang Nayon',
        'North Fairview', 'Novaliches Proper', 'Pasong Putik Proper', 'San Agustin',
        'San Bartolome', 'Sta. Lucia', 'Sta. Monica', 'Fairview'
    ],
    '6': [
        'Apolonio Samson', 'Baesa', 'Balon Bato', 'Culiat', 'New Era', 'Pasong Tamo',
        'Sangandaan', 'Tandang Sora', 'Unang Sigaw', 'Sauyo', 'Talipapa'
    ]
};

/**
 * Initializes a connected Address Component
 * @param {Object} config
 *   - districtSelectId: string (ID of district select element)
 *   - barangaySelectId: string (ID of barangay select or input element)
 *   - streetInputId: string (ID of street input element)
 *   - houseNumberInputId: string (ID of house number input element)
 *   - targetCombinedInputId: string (ID of target address input to hold formatted full string)
 */
function initQCAddressSelector(config) {
    const districtSelect = document.getElementById(config.districtSelectId);
    const barangaySelect = document.getElementById(config.barangaySelectId);
    const streetInput = document.getElementById(config.streetInputId);
    const houseInput = document.getElementById(config.houseNumberInputId);
    const combinedInput = document.getElementById(config.targetCombinedInputId);

    if (!districtSelect || !barangaySelect) return;

    function populateBarangays(districtVal, selectedBarangay = '') {
        barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
        
        if (!districtVal || !QC_BARANGAYS_BY_DISTRICT[districtVal]) {
            barangaySelect.disabled = true;
            return;
        }

        const list = QC_BARANGAYS_BY_DISTRICT[districtVal];
        list.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            if (selectedBarangay && b.toLowerCase() === selectedBarangay.toLowerCase()) {
                opt.selected = true;
            }
            barangaySelect.appendChild(opt);
        });

        barangaySelect.disabled = false;
    }

    function updateCombinedAddress() {
        if (!combinedInput) return;
        const dist = districtSelect.value ? `District ${districtSelect.value}` : '';
        const brgy = barangaySelect.value ? `Brgy. ${barangaySelect.value}` : '';
        const street = streetInput ? streetInput.value.trim() : '';
        const house = houseInput ? houseInput.value.trim() : '';

        const parts = [];
        if (house) parts.push(house);
        if (street) parts.push(street);
        if (brgy) parts.push(brgy);
        if (dist) parts.push(dist);
        parts.push('Quezon City');

        combinedInput.value = parts.join(', ');
    }

    districtSelect.addEventListener('change', function() {
        populateBarangays(this.value);
        updateCombinedAddress();
    });

    barangaySelect.addEventListener('change', updateCombinedAddress);
    if (streetInput) streetInput.addEventListener('input', updateCombinedAddress);
    if (houseInput) houseInput.addEventListener('input', updateCombinedAddress);

    // Initial check
    if (districtSelect.value) {
        populateBarangays(districtSelect.value, barangaySelect.getAttribute('data-initial') || '');
    }
}
