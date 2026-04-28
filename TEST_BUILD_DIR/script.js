function durationNaturalLanguage(duration) {
    // split the duration into days, hours, minutes
    const days = Math.floor(duration / (1000 * 60 * 60 * 24));
    const hours = Math.floor((duration % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));

    // build the natural language string

    let durationNL = '';
    if (days > 0) {
        durationNL += `${days} day${days > 1 ? 's' : ''} `;
    }
    if (hours > 0) {
        if (durationNL.length > 0) {
            durationNL += ', ';
        }
        durationNL += `${hours} h `;
    }
    if (minutes > 0 ) {
        if (hours > 0) {
            unit = '';
        } else {
            unit = 'minute ';
        }
        if (durationNL.length > 0) {
            durationNL += ', ';
        }
        durationNL += `${minutes} ${unit}${minutes > 1 ? 's' : ''}`;
    }
    // trim durationNL
    return durationNL.trim();
}


var timeZone = 'America/Los_Angeles'; // OpenSimulator/SL Time

/**
 * Build time zone selector
 */

// Obtenez l'élément avec l'ID #select-timezone
const selectElement = document.querySelector('#select-timezone');

// Effacez le contenu actuel de l'élément #select-timezone
selectElement.innerHTML = '';

// Créez un nouvel élément select
const select = document.createElement('select');

// Ajoutez l'option pour le fuseau horaire actuel
let option = document.createElement('option');
option.value = timeZone;
option.text = 'OpenSim/SL Time';
select.appendChild(option);

// Obtenez le fuseau horaire de l'utilisateur
const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

// Ajoutez l'option pour le fuseau horaire de l'utilisateur si différent du premier choix
if (userTimeZone !== timeZone) {
    option = document.createElement('option');
    option.value = userTimeZone;
    option.text = userTimeZone;
    select.appendChild(option);
}

// Ajoutez des options pour d'autres fuseaux horaires
// const timeZones = ['Europe/Paris', 'Asia/Tokyo', 'Australia/Sydney']; // Remplacez ceci par la liste des fuseaux horaires que vous voulez inclure
const timeZones = moment.tz.names();

for (const tz of timeZones) {
    if (tz !== timeZone && tz !== userTimeZone) {
        option = document.createElement('option');
        option.value = tz;
        option.text = tz;
        select.appendChild(option);
    }
}

// Ajoutez un gestionnaire d'événements change à l'élément select
select.addEventListener('change', function() {
    // Mettez à jour le fuseau horaire et rafraîchissez le calendrier
    timeZone = this.value;
    refreshCalendar(timeZone);
});

// Ajoutez l'élément select à l'élément #select-timezone
selectElement.appendChild(select);
$(select).select2();

$(select).select2().on('change', function() {
    // Mettez à jour le fuseau horaire et rafraîchissez le calendrier
    timeZone = this.value;
    refreshCalendar(timeZone);
});

/**
 * Display a real time clock for America/Los_Angeles timezone
 */
function updateClock() {
    const slTimeElement = document.querySelector('#sltime');
    setInterval(() => {
        const time = moment().tz('America/Los_Angeles').format('MMM D, YYYY hh:mm:ss A');
        slTimeElement.innerHTML = time;
    }, 1000);
}

// Appeler la fonction pour démarrer l'horloge
updateClock();


function toSlug(text) {
    return window.slugify(text, { lower: true, strict: true });
}

function updateStatus(message) {
    const statusElement = document.querySelector('#notices');
    statusElement.innerHTML = message;
}

function getUniqueWeekNumber(d) {
    // Start from 1970-01-05 which is a monday
    const referenceDate = moment.tz('1970-01-05', timeZone);

    // Calculate the difference in days
    const days = d.diff(referenceDate, 'days');

    return Math.floor(days / 7);
}

/**
 * Fetch and display events
*/
function refreshCalendar(timeZone) {
    const eventsContainer = document.getElementById('events-container');
    eventsContainer.innerHTML = '';
    updateStatus('Warming up the time machine... <i class="fas fa-spinner fa-spin"></i>');

    fetch('events.json')
    .then(response => response.json())
    .then(events => {

        const nowUser = new Date();
        const todayUser = nowUser.toISOString().split('T')[0];

        // Vérifiez que les données sont dans le format attendu
        if (!Array.isArray(events)) {
            throw new Error('Unable to read data source.');
        }
        // add div.status starting to read calendars, with a spinning wheel
        updateStatus('Reading calendars... <i class="fas fa-spinner fa-spin"></i>');        
        const eventsByWeek = {};
        
        events.forEach(event => {
            // Vérifiez que chaque événement a une propriété 'start'
            if (!event.hasOwnProperty('start')) {
                throw new Error('Un événement n\'a pas de propriété \'start\'');
            }
            
            // const startDate = new Date(event.start).toLocaleString(undefined, { timeZone });
            // const endDate = new Date(event.end).toLocaleString(undefined, { timeZone });
            // const startDate = moment(event.start).tz(timeZone);
            // const endDate = moment(event.end).tz(timeZone);
            const startDate = moment(event.start).tz(timeZone).toDate();
            const endDate = moment(event.end).tz(timeZone).toDate();
            
            const weekNumber = getUniqueWeekNumber(moment(startDate));
            
            if (!eventsByWeek[weekNumber]) {
                eventsByWeek[weekNumber] = {};
            }
            
            // const day = startDate.toISOString().split('T')[0]; // Obtenez la date complète au format YYYY-MM-DD
            const day = moment(startDate).tz(timeZone).format('YYYY-MM-DD');

            if (!eventsByWeek[weekNumber][day]) {
                eventsByWeek[weekNumber][day] = [];
            }
            
            eventsByWeek[weekNumber][day].push(event);
        });
        
        updateStatus('Tidying up... <i class="fas fa-spinner fa-spin"></i>');
        
        Object.keys(eventsByWeek).forEach(weekNumber => {
            const weekElement = document.createElement('div');
            weekElement.id = `week-${weekNumber}`;
            weekElement.classList.add('week');
            
            Object.keys(eventsByWeek[weekNumber]).forEach(day => {
                const dayElement = document.createElement('div');

                const dateObject = new Date(`${day}T00:00`);
                dayElement.id = `day-${day}`;

                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const formattedDate = dateObject.toLocaleDateString(undefined, options);

                dayElement.classList.add('day', `day-${toSlug(formattedDate)}`);
                // add "today" class if day is today
                const now = new Date();
                if (now.toISOString().split('T')[0] === day) {
                    dayElement.classList.add('today');
                }

                // Adjust the width of the day element based on the number of events
                // to balance the layout
                const factor = 4;
                const maxPercentage = 40; // Le pourcentage maximum que vous voulez atteindre
                const maxEvents = Math.ceil(maxPercentage / factor);
                const eventCount = Math.min(eventsByWeek[weekNumber][day].length, maxEvents);
                const stretch = factor * eventCount;
                const flexBasis = `calc(8% + ${stretch}%)`;
                dayElement.style.flexBasis = flexBasis;

                dayElement.innerHTML = `<h3>${formattedDate}</h3>`;
                
                eventsByWeek[weekNumber][day].forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.id = `event-${event.hash}`;
                    eventElement.classList.add('event', ...event.tags.map(tag => `tag-${toSlug(tag)}`));
                    // add "ongoing" class if event is ongoing
                    const now = new Date();
                    if (now >= new Date(event.start) && now <= new Date(event.end)) {
                        eventElement.classList.add('ongoing');
                    }
                    
                    // Add "today-user" class if event is today in the user's time zone
                    const eventDayUser = new Date(event.start).toISOString().split('T')[0];
                    if (eventDayUser === todayUser) {
                        eventElement.classList.add('today-user');
                    }
                    
                    const options = { hour: 'numeric', minute: 'numeric' };
                    const startLA = new Date(event.start).toLocaleString(undefined, { timeZone, hour: 'numeric', minute: 'numeric' });
                    const endLA = new Date(event.end).toLocaleString(undefined, { timeZone, hour: 'numeric', minute: 'numeric' });
                    
                    // const duration = new Date(event.end) - new Date(event.start);
                    
                    const teleportLinks = Object.entries(event.teleport)
                    .map(([key, value]) => `<a class="tplink" href="${value}" target="_blank">${key}</a>`)
                    .join(' ');
                    
                    eventElement.innerHTML = `
                    <h4 class=title>${event.title}</h4>
                    <p class=time><span class=start>${startLA}</span> <span class=end>${endLA}</span></p>
                    <p class=description>${event.description}</p>
                    <p class=teleport>${event.hgurl} <span class=tplinks>${teleportLinks}</span></p>
                    <p class=tags>${event.tags}</p>
                    `;
                    
                    dayElement.appendChild(eventElement);
                });
                
                weekElement.appendChild(dayElement);
            });
            
            eventsContainer.appendChild(weekElement);
        });

        updateStatus('');
    })
    // status finished processing
    .catch(error => {
        // Gérez les erreurs ici
        console.error('Erreur:', error);
    });
    
    

}

refreshCalendar(timeZone);


/**
 * Sticky header
 * 
 * included in css:
 *  header {
 *      position: sticky;
 *      top: 0;
 *  }
 * 
 *  .sticky {
 *     background: pink;
 *  } 
 */
const header = document.querySelector('header');
const sticky = header.offsetTop;

window.addEventListener('scroll', function() {
    if (window.pageYOffset > sticky) {
        header.classList.add('sticky');
    } else {
        header.classList.remove('sticky');
    }
});


/**
 * Main menu
 */

document.querySelectorAll('nav li.section').forEach(function(menuItem) {
    menuItem.addEventListener('click', function() {
        // Cacher toutes les sections
        document.querySelectorAll('section').forEach(function(section) {
          section.style.display = 'none';
        });
    
        // Afficher la section correspondante
        var target = this.getAttribute('data-target');
        document.getElementById(target).style.display = 'block';
    });
});

