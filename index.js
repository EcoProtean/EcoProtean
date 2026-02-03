let applets = [];

fetch('index.json')
  .then(response => response.json())
  .then(data => {
    applets = data;
    renderApplets(applets);
  })
  .catch(error => console.error('Error fetching JSON:', error));


  function renderApplets(applets) {
    const appletContainer = document.getElementById('appletContainer');
    appletContainer.innerHTML = '';
  
    applets.forEach(applet => {
      const card = document.createElement('div');
      card.classList.add('card', 'flex-row'); 
  
      const cardBody = document.createElement('div');
      cardBody.classList.add('card-body');
      cardBody.style.minHeight = '200px';
  
      const title1 = document.createElement('h5');
      title1.classList.add('card-title');
      title1.textContent = applet.title1;
      title1.style.color = 'black';
  
      const title2 = document.createElement('h5');
      title2.classList.add('card-title');
      title2.textContent = applet.title2;
      title2.style.color = 'black';
      title2.style.marginBottom = '20px';
  
      const text = document.createElement('p');
      text.classList.add('card-text');
      text.textContent = applet.description;
  
      const button = document.createElement('a');
      button.classList.add('btn');
      button.href = applet.link;
      button.textContent = 'More';
      button.style.backgroundColor = '#29716f';
  
      // Append all elements to cardBody
      cardBody.appendChild(title1);
      cardBody.appendChild(title2);
      cardBody.appendChild(text);
      cardBody.appendChild(button);
  
      // Create img element for the image
      const img = document.createElement('img');
      img.src = applet.imageUrl;
      img.classList.add('card-img-right'); // Assign custom class for styling
      img.style.width = '150px'; // Adjust width as needed
  
      // Add the cardBody and image to the card
      card.appendChild(cardBody);
      card.appendChild(img); // Add the image to the right of the card body
  
      // Add the card to the appletContainer
      appletContainer.appendChild(card);
    });
  }
  