import requests
from bs4 import BeautifulSoup
import datetime
import pytz
import xml.etree.ElementTree as ET
import os

def fetch_gma_schedule(url):
    """
    Fetches the GMA TV schedule from the given URL.

    Args:
        url (str): The URL of the GMA TV schedule.

    Returns:
        str: The HTML content of the page, or None on error.
    """
    try:
        response = requests.get(url)
        response.raise_for_status()  # Raise an exception for bad status codes
        return response.text
    except requests.exceptions.RequestException as e:
        print(f"Error fetching GMA schedule: {e}")
        return None

def parse_schedule(html):
    """
    Parses the HTML content to extract the GMA and GTV TV schedules.

    Args:
        html (str): The HTML content of the GMA TV schedule page.

    Returns:
        dict: A dictionary containing the schedules for GMA and GTV.
              The dictionary has the format:
              {
                  'GMA': [
                      {
                          'title': 'Show Title',
                          'start_time': 'HH:MM AM/PM',
                          'end_time': 'HH:MM AM/PM'
                      },
                      ...
                  ],
                  'GTV': [
                      {
                          'title': 'Show Title',
                          'start_time': 'HH:MM AM/PM',
                          'end_time': 'HH:MM AM/PM'
                      },
                      ...
                  ]
              }
    """
    soup = BeautifulSoup(html, 'html.parser')
    schedules = {}

    # Find the schedule listings.  This should contain both GMA and GTV
    schedule_container = soup.find('div', class_='tv-schedule-container')
    if not schedule_container:
        print("Could not find schedule container")
        return None

    #Find the individual channel schedules
    channel_sections = schedule_container.find_all('div', class_='channel-schedule') # finds each station

    for channel_section in channel_sections:
        channel_name_tag = channel_section.find('h3', class_='channel-name')  # h2 -> h3
        if channel_name_tag:
            channel_name = channel_name_tag.text.strip()
            if channel_name in ('GMA', 'GTV'): # only process GMA and GTV, corrected this if
                schedules[channel_name] = []
                program_items = channel_section.find_all('li', class_='program-item') #programs under each station
                for item in program_items:
                    time_tag = item.find('span', class_='program-time')
                    title_tag = item.find('span', class_='program-title') #changed from p to span
                    if title_element and time_tag:
                        time_str = time_tag.text.strip()
                        title = title_tag.text.strip()
                        time_parts = time_text.split(' - ')
                        if len(time_parts) == 2:
                            start_time = time_parts[0].strip()
                            end_time = time_parts[1].strip()
                            schedules[channel_name].append({'time': time_str, 'title': title})
                        else:
                            print(f"Error: Could not parse time string: {time_text}")
    return schedules



def format_schedule_to_xmltv(schedules):
    """
    Formats the parsed schedule data into XMLTV format.

    Args:
        schedules (dict): A dictionary containing the schedule data for GMA and GTV.
                          See the return type of parse_schedule() for the structure.

    Returns:
        str: The XMLTV formatted string.
    """
    xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml += '<tv generator-info-name="GMA EPG" generator-info-url="https://www.gmanetwork.com">\n'
    
    channel_ids = {}
    counter = 1
    for channel_name in schedules.keys(): #iterates through the keys
        channel_id = f"GMA-{counter}"  # give unique id
        channel_ids[channel_name] = channel_id
        xml += f'  <channel id="{channel_id}">\n'
        xml += f'    <display-name lang="en">{channel_name}</display-name>\n'
        xml += "  </channel>\n"
        counter += 1

    philippine_timezone = pytz.timezone('Asia/Manila')
    now = datetime.datetime.now(philippine_timezone).date()

    for channel_name, programs in schedules.items():
        channel_id = channel_ids[channel_name] #gets the id
        for program in programs:
            title = program['title']
            time_str = program['time']

            try:
                start_time_dt = datetime.datetime.strptime(f"{now} {time_str}", "%Y-%m-%d %I:%M %p")
                end_time_dt = start_time_dt + datetime.timedelta(hours=1)

                start_aware = philippine_timezone.localize(start_time_dt)
                stop_aware = philippine_timezone.localize(end_time_dt)

                xml += f'  <programme start="{start_aware.strftime("%Y%m%d%H%M%S %z")}" stop="{stop_aware.strftime("%Y%m%d%H%M%S %z")}" channel="{channel_id}">\n' #added channel id
                xml += f'    <title lang="en">{title}</title>\n'
                xml += "  </programme>\n"

            except ValueError as e:
                print(f"Error parsing time for '{title}': {e}")
                # Log the error or handle it as needed

    xml += '</tv>\n'
    return xml

if __name__ == "__main__":
    print("GMA EPG script started.")
    gma_url = "https://www.gmanetwork.com/entertainment/schedule/"
    html_content = fetch_gma_schedule(gma_url)
    if html_content:
        schedule_data = parse_schedule(html_content)
        if schedule_data:
            xml_output = format_schedule_to_xmltv(schedule_data)
            output_dir = "output"
            output_filename = os.path.join(output_dir, "gma_clickthecity.xml")
            os.makedirs(output_dir, exist_ok=True)
            with open(output_filename, "w", encoding="utf-8") as f:
                f.write(xml_output)
            print(f"Successfully wrote to: {output_filename}")
        else:
            print("No schedule data parsed.")
    else:
        print("Failed to fetch schedule.")
    print("GMA EPG script finished.")
