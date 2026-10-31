(function(){
  function formatZAR(n){
    return "R" + Number(n).toLocaleString('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2});
  }

  // Hard-coded categories and items (from materials.html)
  const catalog = {
    "Fasteners":[
      {name:"Roofing Nails & Washer (75mm)", price:49.90},
      {name:"Roofing Nails & Washer (90mm)", price:55.90},
      {name:"Tek Screws (65mm)", price:119.00},
      {name:"Tek Screws (85mm)", price:159.00}
    ],
    "Steel":[
      {name:"Rectangular Tube (76x50mm)", price:400.00},
      {name:"Square Tube (25x25mm)", price:212.99},
      {name:"Round Bar (12mm)", price:105.95}
    ],
    "Cladding":[
      {name:"IBR Sheet (0.3mm, 4.8m)", price:219.99},
      {name:"IBR Sheet (0.4mm, 6.0m)", price:582.49}
    ],
    "Welding":[
      {name:"Welding Machine (Arc Master)", price:1499.00},
      {name:"Welding Rods (2.5mm, 5kg)", price:200.00}
    ],
    "Safety Gear":[
      {name:"Welding Gloves", price:23.45},
      {name:"Welding Helmet", price:700.00},
      {name:"Conti Suit", price:690.00}
    ]
  };

  function createRow(){
    const tr=document.createElement("tr");

    // Category select
    const tdCat=document.createElement("td");
    const catSelect=document.createElement("select");
    catSelect.className="material-select category";
    Object.keys(catalog).forEach(cat=>{
      const opt=document.createElement("option");
      opt.value=cat;
      opt.textContent=cat;
      catSelect.appendChild(opt);
    });
    tdCat.appendChild(catSelect);
    tr.appendChild(tdCat);

    // Item select
    const tdItem=document.createElement("td");
    const itemSelect=document.createElement("select");
    itemSelect.className="material-select item";
    tdItem.appendChild(itemSelect);
    tr.appendChild(tdItem);

    // Units input
    const tdUnits=document.createElement("td");
    const qty=document.createElement("input");
    qty.type="number"; qty.min="0"; qty.value="1";
    qty.className="calc-input qty";
    tdUnits.appendChild(qty);
    tr.appendChild(tdUnits);

    // Unit price input
    const tdPrice=document.createElement("td");
    const price=document.createElement("input");
    price.type="number"; price.min="0"; price.step="any";
    price.className="calc-input unit-price";
    tdPrice.appendChild(price);
    tr.appendChild(tdPrice);

    // Subtotal cell
    const tdSub=document.createElement("td");
    tdSub.className="subtotal num";
    tdSub.textContent=formatZAR(0);
    tr.appendChild(tdSub);

    // Populate items when category changes
    function populateItems(){
      const cat=catSelect.value;
      itemSelect.innerHTML="";
      catalog[cat].forEach(item=>{
        const opt=document.createElement("option");
        opt.value=item.price;
        opt.textContent=item.name;
        itemSelect.appendChild(opt);
      });
      updatePrice();
    }

    function updatePrice(){
      const priceVal=parseFloat(itemSelect.value)||0;
      price.value=priceVal.toFixed(2);
      recalc();
    }

    function recalc(){
      const qtyVal=parseFloat(qty.value)||0;
      const unitPrice=parseFloat(price.value)||0;
      const subtotal=qtyVal*unitPrice;
      tdSub.textContent=formatZAR(subtotal);
      recalcAll();
    }

    catSelect.addEventListener("change",populateItems);
    itemSelect.addEventListener("change",updatePrice);
    qty.addEventListener("input",recalc);
    price.addEventListener("input",recalc);

    populateItems(); // initial

    return tr;
  }

  function recalcAll(){
    let total=0;
    document.querySelectorAll("#quote-body tr").forEach(tr=>{
      const sub=tr.querySelector(".subtotal")?.textContent.replace(/R/,"")||"0";
      total+=parseFloat(sub)||0;
    });
    document.getElementById("grand-total").textContent=formatZAR(total);
  }

  function initCategoryQuotation(cfg){
    const tbody=document.getElementById(cfg.tableBodyId);
    tbody.innerHTML="";
    for(let i=0;i<cfg.initialRows;i++){
      tbody.appendChild(createRow());
    }
    document.getElementById("add-row").addEventListener("click",()=>{
      tbody.appendChild(createRow());
    });
    document.getElementById("reset-rows").addEventListener("click",()=>{
      tbody.innerHTML="";
      for(let i=0;i<cfg.initialRows;i++){
        tbody.appendChild(createRow());
      }
      recalcAll();
    });
  }

  window.initCategoryQuotation=initCategoryQuotation;
})();