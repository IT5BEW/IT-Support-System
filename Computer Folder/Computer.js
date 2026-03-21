function getComputerData(formElement, computer_from_php) {
    var selectElement = document.getElementById('computer_select');
    var finalValue = (selectElement && selectElement.value) ? selectElement.value : computer_from_php;
    var hiddenInput = formElement.querySelector('input[name="computer"]');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'computer';
        formElement.appendChild(hiddenInput);
    }
    hiddenInput.value = finalValue;
}

function renderComputerInfo(computer) {
    const form = document.getElementById('computerForm');
    if (!form) return;

    const comMap = JSON.parse(form.getAttribute('data-computers') || '[]');
    const comData = comMap[computer];

    if (comData) {
        if (form.querySelector('[name="equipment_id"]')) form.querySelector('[name="equipment_id"]').value = comData.Equipment_ID;
        if (form.querySelector('[name="comname"]')) form.querySelector('[name="comname"]').value = comData.ComName;
        if (form.querySelector('[name="ip"]')) form.querySelector('[name="ip"]').value = comData.IP;

        const sectionSelect = form.querySelector('[name="section"]');
        if (sectionSelect) sectionSelect.value = comData.Section || '';
    }
}

document.getElementById('computer_select')?.addEventListener('change', function() {
    if (this.style.display !== 'none') {renderComputerInfo(this.value);}
});

function NewComputer(newUser) {
    const computerSelect = document.getElementById('computerSelect');
    const computerForm = document.getElementById('computerForm');
    const computerButton = document.getElementById('computerButton');

    if (newUser) {
        if (computerSelect) computerSelect.style.display = 'none';
        if (computerButton) computerButton.style.display = 'none';
        if (typeof resetNameForm === "function") resetComputerForm();
    } 
    else {
        if (computerSelect) computerSelect.style.display = 'block';
        if (computerButton) computerButton.style.display = 'flex';
        if (computerForm) computerForm.reset();
    }
}

function resetComputerForm(){
    if (document.getElementById("equipment_id")) document.getElementById("equipment_id").value = '';
    if (document.getElementById("comname")) document.getElementById("comname").value = '';
    if (document.getElementById("ip")) document.getElementById("ip").value = '';
    if (document.getElementById("section")) document.getElementById("section").value = '';
}

/*Fix ResetComputer Form*/ 
/*Create Edit Computer*/ 