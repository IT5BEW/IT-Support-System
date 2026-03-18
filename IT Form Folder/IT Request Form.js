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

const nameText = document.getElementById('nameText')

FixCom.addEventListener("click", CheckForm1)
FixETC.addEventListener("click", CheckForm1)

ReInstall.addEventListener("click", CheckForm2)
Broken.addEventListener("click", CheckForm2)
ETC.addEventListener("click", CheckForm2)
ETCText.addEventListener("focusout", CheckForm2)

nameText.addEventListener("focusout", CheckForm3)

var pdfFile

const { PDFDocument } = PDFLib
const { TextAlignment } = PDFLib

async function fillForm() {
  // Check Form
  var check1 = CheckForm1();
  var check2 = CheckForm2();
  var check3 = CheckForm3();
  if(check1&&check2&&check3){
    // Get Thai Font
    const thaiFontUrl = '../- Font/THSarabunNew.ttf'
    const thaiFontBytes = await fetch(thaiFontUrl).then(res => res.arrayBuffer())

    // Fetch the PDF with form fields
    const formUrl = '../- PDF/IT Request Form.pdf'
    const formPdfBytes = await fetch(formUrl).then(res => res.arrayBuffer())

    // Load a PDF with form fields
    const pdfDoc = await PDFDocument.load(formPdfBytes)

    pdfDoc.registerFontkit(fontkit)
    const thaiFont = await pdfDoc.embedFont(thaiFontBytes)

    // Get the form containing all the fields
    const form = pdfDoc.getForm()

    // Get all fields in the PDF by their names
    const Form_CheckBox1 = form.getCheckBox('Check Box1')
    const Form_CheckBox2 = form.getCheckBox('Check Box2')
    const Form_EquipmentID = form.getTextField('Equipment ID')
    const Form_ComName = form.getTextField('Com Name')
    const Form_User = form.getTextField('User')
    const Form_Section = form.getTextField('Section')

    const Form_CheckBox3 = form.getCheckBox('Check Box3')
    const Form_CheckBox4 = form.getCheckBox('Check Box4')
    const Form_CheckBox5 = form.getCheckBox('Check Box5')
    const Form_fill_2 = form.getTextField('fill_2')

    const Form_Text6 = form.getTextField('Text6')
    const Form_Text7 = form.getTextField('Text7')
    const Form_Text8 = form.getTextField('Text8')

    const Form_Text9 = form.getTextField('Text9')
    const Form_Text10 = form.getTextField('Text10')
    const Form_Text11 = form.getTextField('Text11')
    const Form_Text12 = form.getTextField('Text12')

    // Set Font / Size / Alignment
    Form_EquipmentID.setFontSize(16);Form_EquipmentID.updateAppearances(thaiFont);Form_EquipmentID.setAlignment(TextAlignment.Center);
    Form_ComName.setFontSize(16);Form_ComName.updateAppearances(thaiFont);Form_ComName.setAlignment(TextAlignment.Center);
    Form_User.setFontSize(16);Form_User.updateAppearances(thaiFont);Form_User.setAlignment(TextAlignment.Center);
    Form_Section.setFontSize(16);Form_Section.updateAppearances(thaiFont);Form_Section.setAlignment(TextAlignment.Center);
    Form_fill_2.setFontSize(16);Form_fill_2.updateAppearances(thaiFont);Form_fill_2.setAlignment(TextAlignment.Center);
    Form_Text6.setFontSize(16);Form_Text6.updateAppearances(thaiFont)
    Form_Text7.setFontSize(16);Form_Text7.updateAppearances(thaiFont)
    Form_Text8.setFontSize(16);Form_Text8.updateAppearances(thaiFont)
    Form_Text9.setFontSize(16);Form_Text9.updateAppearances(thaiFont);Form_Text9.setAlignment(TextAlignment.Center);
    Form_Text10.setFontSize(16);Form_Text10.updateAppearances(thaiFont);Form_Text10.setAlignment(TextAlignment.Center);
    Form_Text11.setFontSize(16);Form_Text11.updateAppearances(thaiFont);Form_Text11.setAlignment(TextAlignment.Center);
    Form_Text12.setFontSize(16);Form_Text12.updateAppearances(thaiFont);Form_Text12.setAlignment(TextAlignment.Center);

    /*const characterImageField = form.getButton('CHARACTER IMAGE')
    const factionImageField = form.getButton('Faction Symbol Image')*/

    // Fill in the basic info fields
    if(FixCom.checked){Form_CheckBox1.check()}
    if(FixETC.checked){Form_CheckBox2.check()}
    Form_EquipmentID.setText(EquipmentID.innerText)
    Form_ComName.setText(ComName.innerText)
    Form_User.setText(User.innerText)
    Form_Section.setText(Section.innerText)

    if(ReInstall.checked){Form_CheckBox3.check()}
    if(Broken.checked){Form_CheckBox4.check()}
    if(ETC.checked){Form_CheckBox5.check(); Form_fill_2.setText(document.getElementById('ETCText').value);}
    else{Form_fill_2.setText('')}

    // Fill in Cause Text Fields
    Form_Text6.setText(Cause1.value)
    Form_Text7.setText(Cause2.value)
    Form_Text8.setText(Cause3.value)

    // Fill in Name Field
    Form_Text9.setText(nameText.value)

    // Fill in Date
    const date = new Date();
    const result = date.toLocaleDateString('th-TH', {year: 'numeric',month: 'short',day: 'numeric',})
    const splitDate = result.split(' ')
    Form_Text10.setText(splitDate[0])
    Form_Text11.setText(splitDate[1])
    Form_Text12.setText(splitDate[2])

    /* Example for Image Input
    factionImageField.setImage(emblemImage)*/

    // Workaround to bind Thai font
    const rawUpdateFieldAppearances = form.updateFieldAppearances.bind(form);
    form.updateFieldAppearances = function () {
      return rawUpdateFieldAppearances(thaiFont);
    };

    // Serialize the PDFDocument to bytes (a Uint8Array)
    const pdfBytes = await pdfDoc.save()

    // Save form to global variable
    pdfFile = pdfBytes
    alert("กรอกฟอร์มสำเร็จ")
  }
  else{alert("กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง")}
}

function UnlockFormETC(){
  var formUnlock = !document.getElementById("ETC").checked
  document.getElementById('ETCText').disabled = formUnlock
  if(formUnlock){if(document.getElementById('ETCText').classList.contains("required")){document.getElementById('ETCText').classList.remove("required");}}
}

function DownloadForm(){
    if(pdfFile != undefined){
      // Trigger the browser to download the PDF document
      const currentDate = new Date();
      const isoDate = currentDate.toISOString().slice(0, 10);
      var fileName = prompt("กรุณาตั้งชื่อไฟล์", isoDate);
      if(fileName != null && fileName != ""){download(pdfFile, fileName + ".pdf", "application/pdf")}
    }
    else{
      alert("กรุณากรอกฟอร์มด้วยครับ")
    }
}

function ClearForm(){
  let deleteForm = "คุณต้องการล้างข้อมูลในฟอร์มหรือไม่";
  if (confirm(deleteForm) == true) {
    document.getElementById('mainForm').reset()
  }
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
  var check = CheckForm(nameText);
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