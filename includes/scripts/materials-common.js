

// materials-common.js
document.addEventListener('DOMContentLoaded', function(){
  // Toggle sections
  document.querySelectorAll('.section-header').forEach(header=>{
    header.addEventListener('click', ()=> header.parentElement.classList.toggle('open'));
  });

  // Optional: if you later add data/catalog.json, this helper will render cards into a container
  window.renderCatalogSection = async function(sectionKey, containerId){
    try {
      const res = await fetch('/data/catalog.json');
      if (!res.ok) return;
      const catalog = await res.json();
      const items = catalog[sectionKey] || [];
      const container = document.getElementById(containerId);
      if (!container) return;
      // find the first .section-content inside container and populate
      const content = container.querySelector('.section-content') || container;
      content.innerHTML = items.map(it => `
        <div class="card material-card" data-name="${it.name}" data-spec="${it.spec}" data-price="${it.price}">
          <div class="material-img">Image</div>
          <h2>${it.name}</h2>
          <p class="muted">${it.spec}</p>
          <p class="price">R${it.price}</p>
        </div>
      `).join('');
    } catch(e){
      console.warn('catalog load failed', e);
    }
  };
});