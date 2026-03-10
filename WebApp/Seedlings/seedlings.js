let applets = [];

// Fetch the seedlings data
fetch('seedlings.json')
  .then(response => response.json())
  .then(data => {
    applets = data; 
    renderApplets(applets); // Render all seedlings initially
  })
  .catch(error => console.error('Error fetching JSON:', error));

// Render seedlings into the container
function renderApplets(applets) {
  const appletContainer = document.getElementById('appletContainer');
  appletContainer.innerHTML = ''; // Clear existing content

  applets.forEach(applet => {
    const card = document.createElement('div');
    card.classList.add('card');

    const img = document.createElement('img');
    img.src = applet.imageUrl;
    img.classList.add('card-img-top');
    img.height = 180;

    const cardBody = document.createElement('div');
    cardBody.classList.add('card-body');
    cardBody.style.minHeight = '200px';

    const title1 = document.createElement('h5');
    title1.classList.add('card-title');
    title1.textContent = applet.title1;

    const title2 = document.createElement('h5');
    title2.classList.add('card-title');
    title2.textContent = applet.title2;

    const text = document.createElement('p');
    text.classList.add('card-text');
    text.textContent = truncateText(applet.description, 50); 
    text.id = `description-${applet.title1}`;

    const sources = document.createElement('a');
    sources.classList.add('card-text');

    const readMoreButton = document.createElement('button');
    readMoreButton.classList.add('btn', 'btn-link');
    readMoreButton.textContent = 'Read more';
    readMoreButton.addEventListener('click', function () {
        toggleDescription(text, applet.description, readMoreButton);
    });

    cardBody.appendChild(title1);
    cardBody.appendChild(title2);
    cardBody.appendChild(text);
    cardBody.appendChild(readMoreButton);

    card.appendChild(img);
    card.appendChild(cardBody);
    appletContainer.appendChild(card);
  });
}

// Truncate the description to fit within a limited space
function truncateText(text, limit) {
  if (text.length > limit) {
    return text.substring(0, limit) + '...'; 
  }
  return text;
}

// Toggle the full description of the seedling
function toggleDescription(textElement, fullText, button) {
  if (button.textContent === 'Read more') {
    textElement.textContent = fullText; 
    button.textContent = 'Show less';
  } else {
    textElement.textContent = truncateText(fullText, 100); 
    button.textContent = 'Read more';
  }
}

// Search functionality
const searchButton = document.getElementById('searchButton');
const searchInput = document.getElementById('searchInput');

// Event listener for the search button click
searchButton.addEventListener('click', function () {
  const query = searchInput.value.toLowerCase(); // Get the search query and make it lowercase
  const filteredApplets = applets.filter(applet => {
    return applet.title1.toLowerCase().includes(query) || 
           applet.title2.toLowerCase().includes(query) || 
           applet.description.toLowerCase().includes(query); // Search in title and description
  });
  renderApplets(filteredApplets); // Render the filtered seedlings
});

// Optional: You can add a listener for the "Enter" key as well to trigger the search
searchInput.addEventListener('keyup', function (e) {
  if (e.key === 'Enter') {
    searchButton.click(); // Trigger the search button click event on Enter key press
  }
});