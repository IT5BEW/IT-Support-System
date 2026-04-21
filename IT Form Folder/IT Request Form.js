const EquipmentID = document.getElementById('EquipmentID')
const ComName = document.getElementById('ComName')
const User = document.getElementById('User')
const Section = document.getElementById('Section')
const FixCom = document.getElementById("FixCom")
const FixETC = document.getElementById("FixETC")

const ReInstall = document.getElementById("ReInstall")
const Broken = document.getElementById("Broken")
const ETC = document.getElementById("ETC")
const ETCText = document.getElementById('ETCText')

const Cause1 = document.getElementById('cause1')
const Cause2 = document.getElementById('cause2')
const Cause3 = document.getElementById('cause3')

FixCom.addEventListener("click", CheckForm1)
FixETC.addEventListener("click", CheckForm1)

ReInstall.addEventListener("click", CheckForm2)
Broken.addEventListener("click", CheckForm2)
ETC.addEventListener("click", CheckForm2)
ETCText.addEventListener("focusout", CheckForm2)

function UnlockFormETC(){
  var formUnlock = !document.getElementById("ETC").checked
  document.getElementById('ETCText').disabled = formUnlock
  if(formUnlock){if(document.getElementById('ETCText').classList.contains("required")){document.getElementById('ETCText').classList.remove("required");}}
}

function ClearForm(){
  let deleteForm = "คุณต้องการล้างข้อมูลในฟอร์มหรือไม่";
  if (confirm(deleteForm) == true) {document.getElementById('mainForm').reset()}
  document.getElementById("check1").hidden = true;
  document.getElementById("check2").hidden = true;
  document.getElementById("check3").hidden = true;
  const output = document.getElementById('output-image');
  output.src = "";
  output.style.display = 'none';
}

function CheckForm(element){
  var check = true
  if(element.value == ""){element.classList.add("required"); check = false;}
  else if(element.classList.contains("required")){element.classList.remove("required");}
  return check;
}

function CheckForm1(){
  var check = true;
  var check5 = FixCom.checked;
  var check6 = FixETC.checked;
  if(!((check5||check6))){check = false;}
  document.getElementById("check1").hidden = check;
  return check;
}

function CheckForm2(){
  var check = true;
  var check1 = ReInstall.checked;
  var check2 = Broken.checked;
  var check3 = ETC.checked;
  var check4 = ETCText;
  if(check1||check2 ||check3){if(check3){check = CheckForm(check4);}}
  else{check = false}
  document.getElementById("check2").hidden = check;
  return check;
}

function CheckForm3(){
  const selectedRadio = document.querySelector('input[name="signature"]:checked');
  var check = true;
  if (!selectedRadio) {check = false;}
  document.getElementById("check3").hidden = check;
  return check;
}

// Limit Text in TextBox
function restrictInputByWidth(inputElement, offset = 5) {
  function getTextWidth(text, font) {
    const canvas = restrictInputByWidth.canvas || (restrictInputByWidth.canvas = document.createElement("canvas"));
    const context = canvas.getContext("2d");
    context.font = font;
    return context.measureText(text).width;
  }

  function willOverflow(el, nextText) {
    const style = window.getComputedStyle(el);
    const font = `${style.fontSize} ${style.fontFamily}`;
    const padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
    const availableWidth = el.clientWidth - padding - offset;
    return getTextWidth(nextText, font) > availableWidth;
  }

  inputElement.addEventListener('keydown', function(e) {
    const isControlKey = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(e.key);
    if (isControlKey || this.selectionStart !== this.selectionEnd) return;

    const simulatedText = this.value.slice(0, this.selectionStart) + e.key + this.value.slice(this.selectionEnd);
    if (e.key.length === 1 && willOverflow(this, simulatedText)) {
      e.preventDefault();
    }
  });

  inputElement.addEventListener('paste', function(e) {
    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
    const simulatedText = this.value.slice(0, this.selectionStart) + pasteData + this.value.slice(this.selectionEnd);
    if (willOverflow(this, simulatedText)) {
      e.preventDefault();
    }
  });
}

// Apply Limit to Text Box
document.querySelectorAll('.limit-width').forEach(el => {
  restrictInputByWidth(el);
});

document.getElementById("mainForm")?.addEventListener("submit", function(event) {
  var check7 = CheckForm1()
  var check8 = CheckForm2()
  var check9 = CheckForm3()
  if (!check7 || !check8 || !check9) {
    event.preventDefault();
    return;
  }

  const submitter = event.submitter;
  if (submitter.name === "edit_form") {confirmMessage = "ยืนยันการแก้ไขใบขอแจ้งซ่อมใช่หรือไม่?";} 
  else {confirmMessage = "ยืนยันการส่งใบขอแจ้งซ่อมหรือไม่?";} 
  
  if (!confirm(confirmMessage)) {
    event.preventDefault();
  }
});


function previewImage(event) {
    const input = event.target;
    const output = document.getElementById('output-image');

    if (!output) {
        console.error("หา Element ไอดี 'output-image' ไม่เจอครับ!");
        return;
    }

    // ตรวจสอบว่ามีการเลือกไฟล์อย่างน้อย 1 ไฟล์หรือไม่
    if (input.files && input.files[0]) {
        if(input.files[0].size > 50*(1024*1024)){
          alert("ขนาดของภาพใหญ่เกินไป ขนาดต้องไม่เกิน 50MB");
          input.value = "";
          output.src = "";
          output.style.display = 'none';
          return;
        };

        const reader = new FileReader();
        reader.onload = function() {
            output.src = reader.result;
            output.style.display = 'block'; // แสดงรูปเมื่อโหลดเสร็จ
        };

        reader.readAsDataURL(input.files[0]);
    } else {
        // หากไม่มีไฟล์ (เช่น กด Cancel) ให้ซ่อนรูปและล้างค่า src
        output.src = "";
        output.style.display = 'none';
    }
}
/*
add input if no com???
*/