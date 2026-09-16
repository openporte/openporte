<?php

if ( ! defined( 'ABSPATH' ) ) exit;


function openporte_options_page_html()
{
  wp_enqueue_script(
    'altcha-admin-js',
    OpenPortePlugin::$admin_script_src,
    array(),
    OPENPORTE_VERSION,
    true
  );
  wp_enqueue_style(
    'altcha-admin-styles',
    OpenPortePlugin::$admin_css_src,
    array(),
    OPENPORTE_VERSION,
    'all'
  );
?>
  <div class="altcha-head">
    <div class="altcha-logo">
      <svg width="128" height="128" version="1.1" viewBox="0 0 270.93 270.93" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
        <title>OpenPorte</title>
        <g transform="translate(-66.01 -51.53)" style="mix-blend-mode:normal"><path x="81.531723" y="67.051056" width="860.89001" height="890.2049" d="m87 56.17h228.9c10.28-0.3327 17.68 9.497 16.39 18.86v227.2c0.411 11.77-11.81 16.56-21.98 15.61h-223.3c-12.3 0.4051-17.39-11.54-16.39-21.5v-224.3c-0.2136-8.471 7.596-16.02 16.36-15.81z" fill="#fff" stroke="#0d4051" stroke-linecap="round" stroke-linejoin="round" stroke-width="9.279" style="mix-blend-mode:normal;paint-order:stroke fill markers"/>
        <path d="m246.8 241.8c-3.93-2.401-7.827-4.856-11.74-7.285-0.0459-8.444-0.0919-16.89-0.1378-25.33h11.68v-18.77c-1.237-0.3464-2.568-0.1458-3.847-0.199-0.047 4.996-0.0939 9.993-0.1409 14.99h-7.685c-0.0242-16.64 0.0651-33.28-0.0847-49.91-0.0279-3.063-0.0964-6.167-1.064-9.106-1.434-5.57-4.385-10.72-8.484-14.75-3.146-3.271-7.091-5.604-11.17-7.52-0.9006-0.1297-0.3227-0.8302-0.1452-1.376 0.9814-2.505 1.963-5.01 2.944-7.516 4.272 2.063 8.461 4.41 11.95 7.673 4.096 3.686 7.456 8.163 9.808 13.15 1.694 3.608 2.954 7.453 3.568 11.4 0.3946 5.933 0.2891 11.89 0.4024 17.83 0.0349 2.988 0.0698 5.977 0.1047 8.965 1.293 0.0522 2.585 0.1044 3.878 0.1565-0.0897-8.536-0.0304-17.08-0.2718-25.61 8e-3 -2.26-0.7779-4.413-0.9791-6.643 0.7797-1.204 2.616-1.583 3.781-2.482 3.102-1.746 6.159-3.584 9.326-5.211 0.1372 15.12 0.0546 30.24 0.0727 45.36-1.245 0.052-2.49 0.1039-3.736 0.1559v12.18h3.714c-0.0147 12.6 0.0731 25.2 0.1327 37.8-1.99 0.05-3.979 0.1-5.969 0.15v-11.69h-5.837c-0.0308 7.863-0.0242 15.76-0.0662 23.6zm-2.623-104.1c-1.741-2.684-2.916-5.704-4.648-8.403-2.733-4.457-6.398-8.303-10.43-11.61-3.238-2.49-6.789-4.546-10.49-6.254 0.0204-0.7291 0.5595-3.713 1.66-1.976 7.24 3.936 14.37 8.078 21.58 12.07 4.901 2.801 9.908 5.425 14.74 8.339 1.049 1.121-2.077 1.571-2.787 2.374-3.216 1.813-6.423 3.641-9.634 5.465zm-34.86-15.38c-1.908-0.117-3.765-0.7254-5.688-0.696-2e-3 -12.78-0.0546-25.57 0.0711-38.35 5.784 3.227 11.54 6.507 17.17 9.998 0.1478 1.373-1.014 3.047-1.412 4.527-3.022 7.722-5.953 15.49-9.24 23.1-0.2718 0.474-0.4254 1.114-0.9048 1.424zm-53.04 119.5c0.0592-3.492-0.0229-6.993 0.197-10.48 1.11 0.0718 3.627-0.7872 3.915 0.391v10.11c-1.37 0.0233-2.742 0.0166-4.112-0.0213zm-8.887-11.92c-1.33-0.1122-3.628 0.5966-2.921-1.557 0.0158-12.04-0.0426-24.09 0.0259-36.13 1.155-0.4405 2.465-0.1809 3.691-0.2504v-12.2c-1.199-0.0423-2.427 0.1228-3.598-0.1853-0.4007-1.586-0.0708-3.462-0.1838-5.156 0.0195-13.41 0.1288-26.82 0.2006-40.24 4.367 2.506 8.753 4.979 13.1 7.524-0.3334 2.726-1.148 5.396-1.086 8.171-0.1756 8.11-0.1601 16.22-0.2382 24.33 1.293-0.0522 2.585-0.1044 3.878-0.1565 0.107-8.697 0.1567-17.4 0.2993-26.09 0.9619-4.145 1.903-8.336 3.845-12.16 4.282-8.917 11.76-16.33 20.87-20.24 1.652-0.2897 1.763 2.572 2.541 3.732 0.5994 1.5 1.192 3.003 1.768 4.512-4.479 1.601-8.523 4.33-11.85 7.721-5.227 5.033-8.475 11.96-9.365 19.13-0.3829 7.294-0.2208 14.6-0.2815 21.91-5e-3 10.89-2e-3 21.77-4e-3 32.66h-7.685c-0.047-4.996-0.0939-9.993-0.1409-14.99-1.282-0.052-2.564-0.1039-3.847-0.1559v19.12h11.67v11.14h-11.67c0.2081-1.205-0.2805-2.466-1.745-2.106-1.452-0.0296-2.904-0.0119-4.356-0.0168v11.67c-0.9727 2.9e-4 -1.945 5.8e-4 -2.918 1e-3zm11.69-92.06c-4.442-2.415-8.818-4.956-13.18-7.503 4.874-3.069 9.992-5.746 14.99-8.614 7.086-3.91 14.07-8.011 21.21-11.81 1.072-0.9144 2.208-0.758 2.269 0.8002 0.0854 1.673-2.822 1.801-3.98 2.847-9.295 4.947-16.68 13.3-20.65 23.03-0.2077 0.4169-0.3537 0.8853-0.65 1.251zm54.04 99.17c-1.127 0.0248-2.255 0.0495-3.382 0.0743v-7.195c2.255 0.0495 4.51 0.099 6.765 0.1485v6.898c-1.128 0.0248-2.255 0.0495-3.383 0.0743zm-12.82-3.979c-1.311-0.3288-4.081 0.8397-4.281-0.8874-0.1273-2.424-0.0667-4.851-0.0811-7.277h8.489v-12.72c1.859-1.186 3.31-3.259 3.038-5.548 0.0899-3.19-2.861-6.124-6.069-5.928-0.7813 0.0488 0.5365-1.342 0.0902-2.081 0.0403-2.735 0.0161-5.47 0.0228-8.204 3.493-0.1541 6.987-0.2664 10.48-0.1981 4.876 0.0188 9.752 0.0377 14.63 0.0565 1.7 0.9633 3.582 2.265 3.675 4.413 0.2434 5.628 0.0879 11.27 0.1293 16.9v4.288h-15.12v9.02h-10.86c-0.0488 2.697-0.0976 5.394-0.1465 8.092-1.332 0.0244-2.663 0.0489-3.995 0.0733zm24.95-2.859h-5.173c-0.0514-3.272-0.0887-6.544-0.1326-9.816 3.493 2e-5 6.986 5e-5 10.48 7e-5v9.816c-1.724-5e-5 -3.449-1e-4 -5.173-1.5e-4zm-5.808-53.32c-1.335-0.1741-3.376 0.3898-4.184-0.3956-0.2001-3.753 0.333-7.75-1.433-11.22-1.264-2.817-3.668-5.012-6.304-6.543-1.899-0.8744-3.977-1.194-6.014-1.58v-8.19c3.459 0.2305 6.951 0.8182 10.04 2.478 3.356 1.66 6.207 4.299 8.329 7.364 1.921 3.087 3.524 6.566 3.505 10.27 0.0958 2.605 0.1361 5.212 0.2026 7.818-1.38 1e-5 -2.76 3e-5 -4.141 4e-5z" fill="#3b82a8" style="mix-blend-mode:normal"/>
        <path d="m104.6 258.6c-4.899-0.1852-9.521 4.255-9.001 9.244 0.04348 4.531-0.0913 9.069 0.07565 13.6 0.5854 4.628 5.334 7.785 9.828 7.349 1.271 0.3201 1.564-0.3384 1.35-1.504v-2.955c-2.252 0.0657-4.987 0.1365-6.261-2.125-0.905-1.89-0.3681-4.067-0.5141-6.097 0.0503-3.277-0.1035-6.567 0.0823-9.835 0.5599-2.51 3.332-3.477 5.643-3.215 1.384 0.4327 1.032-0.8703 1.05-1.804-0.1035-0.8313 0.2051-2.075-0.1504-2.654h-2.102zm2.253 4.458c0.8535 2e-3 1.717 2e-3 2.571 0h-2.571zm2.571 0c2.255-0.0722 4.974-0.1221 6.254 2.133 0.9063 1.886 0.3673 4.061 0.5141 6.089-0.0503 3.277 0.1035 6.568-0.0823 9.836-0.5452 2.53-3.329 3.475-5.643 3.214-1.382-0.4334-1.022 0.8761-1.043 1.804 0.1035 0.8313-0.2051 2.075 0.1504 2.654 3.002 0.1491 6.247-0.2184 8.465-2.489 2.325-1.974 2.8-5.085 2.632-7.957-0.0423-4.133 0.0896-8.273-0.0756-12.4-0.5825-4.63-5.337-7.774-9.828-7.341-1.272-0.3227-1.554 0.3437-1.344 1.504v2.955zm0 21.27c-0.8535-2e-3 -1.717-2e-3 -2.571 0h2.571zm92.69-25.73v30.19h4.479v-25.73c4.028 0.0352 8.065-0.0711 12.09 0.0544 2.305 0.3766 2.531 2.846 2.391 4.722 0.3447 2.032-0.9922 4.362-3.294 4.077h-8.637v4.458c3.475-0.0675 6.967 0.1494 10.43-0.1374 3.779-0.6514 6.409-4.489 5.961-8.24 0.129-2.568-0.1505-5.374-2.192-7.206-1.923-2.125-4.851-2.3-7.515-2.187-4.57-2e-5 -9.14-3e-5 -13.71-5e-5zm69.76 0v8.195h-2.866v4.479h2.866c0.033 4.045-0.0676 8.094 0.053 12.14 0.3753 3.415 3.855 5.794 7.176 5.379h4.023v-4.479c-1.862-0.0685-3.755 0.1472-5.594-0.1274-1.767-0.7378-1.017-2.858-1.178-4.341v-8.567h6.772v-4.479h-6.772v-8.195c-1.493 2e-5 -2.986 4e-5 -4.48 5e-5zm-141.4 8.195c-2.807-0.1031-4.89 2.718-4.479 5.381v24.81h4.479v-25.71c2.934 0.0779 5.899-0.1666 8.812 0.1433 1.972 0.5435 2.67 2.679 2.44 4.52-0.1052 2.067 0.2467 4.196-0.2548 6.212-0.6826 1.894-2.778 2.341-4.559 2.16h-3.891v4.479c3.249-0.175 6.819 0.6457 9.674-1.343 2.711-1.73 3.777-5.022 3.51-8.1-0.1272-3.207 0.6078-6.799-1.66-9.47-1.816-2.509-4.989-3.307-7.934-3.081-2.046 1e-5 -4.092 2e-5 -6.138 4e-5zm25.52 0c-2.858-0.1173-5.126 2.683-4.692 5.445 0.0639 3.86-0.1362 7.736 0.1166 11.58 0.5543 3.18 3.793 5.356 6.942 4.964h11.6v-4.479c-4.28-0.0213-8.564 0.0425-12.84-0.0319-1.866-0.5034-1.227-2.684-1.34-4.115v-8.889c3.264 0.0225 6.532-0.0454 9.793 0.0345 2.33 0.4792 1.837 4.738-0.6682 4.36h-6.577v4.458c2.836-0.0894 5.705 0.2083 8.513-0.2122 3.281-0.8217 5.182-4.364 4.616-7.591-0.3852-3.43-3.813-5.954-7.196-5.529-2.756 5e-5 -5.513 9e-5 -8.269 1.3e-4zm24.92 0c-2.808-0.1117-4.943 2.678-4.522 5.36v16.63h4.479v-17.56c2.827 0.0387 5.664-0.0803 8.486 0.065 2.094 0.378 3.03 2.625 2.766 4.554v12.94h4.479c-0.0409-5.041 0.0844-10.09-0.0677-15.12-0.4736-4.258-4.738-7.366-8.924-6.87-2.232-1e-5 -4.465-3e-5 -6.697-4e-5zm56.28 0c-4.537-0.1346-8.437 4.201-7.94 8.691 0.1725 3.131-0.5777 6.499 1.062 9.354 1.612 2.923 5.018 4.254 8.231 3.948 2.993-7e-3 6.36 0.3404 8.685-1.975 2.317-1.888 2.885-4.943 2.7-7.769-0.0734-3.265 0.5369-6.967-1.983-9.55-1.88-2.322-4.938-2.884-7.761-2.7h-2.994zm23.95 0c-3.626-0.1389-6.781 3.309-6.326 6.908v15.09h4.479c0.0219-5.369-0.044-10.74 0.0332-16.11 0.4992-1.933 2.739-1.33 4.219-1.426 0.7486-0.1844 2.246 0.3798 2.498-0.3007v-4.157c-1.635-4e-5 -3.269-7e-5 -4.904-1e-4zm30.72 0c-2.858-0.1174-5.126 2.683-4.692 5.445 0.0638 3.86-0.136 7.736 0.1164 11.58 0.5536 3.18 3.793 5.356 6.942 4.964h11.6v-4.479c-4.28-0.0213-8.564 0.0425-12.84-0.0319-1.866-0.5034-1.227-2.684-1.34-4.115v-8.889c3.264 0.0225 6.532-0.0454 9.794 0.0345 2.33 0.4791 1.837 4.738-0.6682 4.36h-6.578v4.458c2.836-0.0894 5.705 0.2083 8.513-0.2122 3.281-0.8217 5.182-4.364 4.616-7.591-0.3852-3.43-3.813-5.954-7.196-5.529-2.756 5e-5 -5.513 9e-5 -8.269 1.3e-4zm-54.67 4.479c2.04 0.1046 4.143-0.2473 6.13 0.2601 1.862 0.7304 2.306 2.813 2.128 4.596-0.1044 2.003 0.2456 4.067-0.2548 6.019-0.6826 1.894-2.778 2.341-4.559 2.16-2.072-0.1599-4.631 0.6082-6.125-1.285-1.289-1.722-0.608-3.983-0.7802-5.976-4e-3 -1.829-0.2834-4.075 1.554-5.188 0.5529-0.3918 1.231-0.5903 1.906-0.5851zm-37.93-187.8c-5.828 3.203-11.55 6.61-17.31 9.934 3.507 9.253 7.204 18.44 10.91 27.61 0.8436 2.703 4.205 0.2569 6.201 0.4994 0.5056-4.051 0.2104-8.2 0.3249-12.29-0.012-8.585 0.0709-17.17-0.1345-25.76zm-19.92 14.86c-7.289 3.724-14.33 7.941-21.51 11.88-7.779 4.39-15.64 8.646-23.35 13.16 1.438 1.654 3.865 2.478 5.734 3.713 2.287 0.7886 4.579 3.865 6.915 2.396 11.87-6.621 23.65-13.42 35.61-19.89 1.764-0.3027 0.207-1.819 0.0106-2.86-1.142-2.796-2.14-5.662-3.416-8.397zm-47.05 28.86v60.55h15.92v-7.959c-1.172-0.249-3.58 0.6879-3.724-0.8564-3.5e-4 -14.9 0.017-29.8 0.0266-44.7-4.058-2.368-8.22-4.563-12.22-7.028zm0 64.79v37.92c-1.519 0.0511-3.038 0.1021-4.557 0.1532-2.288 3.8-4.646 7.56-6.85 11.41 11.8 0.2248 23.61 0.0965 35.42 0.1301 0.0611-3.416-0.0271-6.84 0.2121-10.25 3.811-0.2979 7.641-0.2754 11.46-0.398 0.0478-3.525 0.0957-7.051 0.1436-10.58h-11.68c0.5948-2.484-2.132-2.189-3.813-2.122-0.6793 0.2339-2.337-0.5106-2.288 0.4511v11.24c-1.949-0.0501-3.897-0.1001-5.846-0.15-2e-3 -12.6-5e-3 -25.2-8e-3 -37.8-4.062 2.3e-4 -8.124-1.8e-4 -12.19-1e-3zm91.57-93.65c-1.653 3.458-2.966 7.078-4.292 10.67 2.702 1.731 5.662 3.102 8.419 4.774 9.781 5.441 19.48 11.03 29.32 16.37 3.911-2.111 7.818-4.282 11.61-6.617-5.248-3.181-10.72-6.019-16.05-9.081-9.67-5.365-19.25-10.9-29-16.12zm46.91 28.96c-4.052 2.312-8.107 4.621-12.17 6.908-2e-3 15.15-5e-3 30.3-8e-3 45.45-1.245 0.052-2.491 0.104-3.736 0.1559v7.935h15.92c-3e-5 -20.15 2e-5 -40.3 1.5e-4 -60.44zm-12.24 64.69c0.0569 12.6 0.1139 25.2 0.1708 37.8-1.99 0.0499-3.979 0.0999-5.969 0.15v-11.69h-5.826c-0.0363 7.811-0.1282 15.62-0.0994 23.43 11.79-3e-3 23.58 0.0254 35.37-0.2079-2.197-3.847-4.526-7.617-6.795-11.42-1.538-0.0511-3.075-0.1021-4.613-0.1532v-37.92c-4.08-2.2e-4 -8.161 1.6e-4 -12.24 1e-3zm-57-42.97c-6.199 0.2749-12.41 2.751-16.49 7.536-3.684 4.187-5.65 9.78-5.432 15.35-0.1375 6.163-0.0785 12.33-0.0961 18.49-2.193-0.145-4.678 0.1995-5.948 2.248-1.447 2.562-0.6165 5.705-0.9179 8.52-0.0522 6.403-0.0222 12.81-0.0314 19.21h10.61v-4.51h8.754v4.51h6.632v-8.161c-2.944-1.739-4.222-5.956-2.147-8.837 1.049-1.821 3.11-2.576 5.065-2.951v-10.02c-4.554-0.0471-9.108-0.0942-13.66-0.1415-7e-3 -6.985-0.2749-13.98 0.1194-20.96 0.7614-6.098 5.905-11.36 12.08-11.96 1.154 0.043 1.77-0.375 1.462-1.583-1.1e-4 -2.248 1.7e-4 -4.495 4.2e-4 -6.743zm-18.3 71.36v10.61h8.489v-10.61h-8.489zm-10.61 10.88c1.6e-4 3.493 3.3e-4 6.986 4.9e-4 10.48h10.61c-7e-5 -3.493-2.2e-4 -6.986-4.4e-4 -10.48h-10.61zm17.51 4.508c-3.3e-4 1.99-5e-4 3.979-9.9e-4 5.969 1.946-0.0554 3.892-0.1106 5.838-0.1658v-5.803c-1.946 1e-5 -3.891-1e-5 -5.837-6e-5z" fill="#0d4051" style="mix-blend-mode:normal;text-orientation:upright"/></g>
        <metadata><rdf:RDF><cc:Work rdf:about=""><dc:title>OpenPorte</dc:title><dc:date>2026-07-03</dc:date><dc:creator><cc:Agent><dc:title>Jean-Christophe Berthon</dc:title></cc:Agent></dc:creator><dc:subject><rdf:Bag><rdf:li>openporte</rdf:li></rdf:Bag></dc:subject><dc:description>Logo</dc:description><dc:contributor><cc:Agent><dc:title>Assisted by AI - Gemini</dc:title></cc:Agent></dc:contributor><cc:license rdf:resource="http://creativecommons.org/licenses/by-nc-sa/4.0/"/></cc:Work><cc:License rdf:about="http://creativecommons.org/licenses/by-nc-sa/4.0/"><cc:permits rdf:resource="http://creativecommons.org/ns#Reproduction"/><cc:permits rdf:resource="http://creativecommons.org/ns#Distribution"/><cc:requires rdf:resource="http://creativecommons.org/ns#Notice"/><cc:requires rdf:resource="http://creativecommons.org/ns#Attribution"/><cc:prohibits rdf:resource="http://creativecommons.org/ns#CommercialUse"/><cc:permits rdf:resource="http://creativecommons.org/ns#DerivativeWorks"/><cc:requires rdf:resource="http://creativecommons.org/ns#ShareAlike"/></cc:License></rdf:RDF></metadata>
      </svg>
    </div>

    <div style="flex-grow: 1;">
      <div class="altcha-title"><?php echo esc_html__('OpenPorte', 'openporte'); ?></div>
      <div class="altcha-subtitle"><?php echo esc_html__('A Privacy-Friendly Captcha Alternative.', 'openporte'); ?></div>
    </div>

    <div>
      <div style="margin-bottom: 0.3rem;"><b><?php echo esc_html__('Do you like OpenPorte?', 'openporte'); ?></b></div>
      <div style="display:flex;gap: 0.5rem;">
        <a href="https://wordpress.org/support/plugin/openporte/reviews/" target="_blank" rel="noopener noreferrer" style="display: inline-flex; gap: 0.5rem;">
          <span><?php echo esc_html__('Review it!', 'openporte'); ?></span>
        </a>
      </div>
    </div>
  </div>

  <div class="wrap">

    <hr>

    <form action="options.php" method="post">
      <?php
      settings_errors();
      settings_fields('openporte_options');
      do_settings_sections('openporte_admin');
      submit_button();
      ?>
    </form>

    <div style="opacity: 0.8;">
      <p><?php
        echo sprintf(
          /* translators: %1$s is the plugin version, and %2$s is the ALTCHA widget version */
          esc_html__(
              'OpenPorte Spam Protection for WordPress, plugin version %1$s, ALTCHA widget version %2$s',
              'openporte',
          ),
          esc_html( OpenPortePlugin::$version ),
          esc_html( OpenPortePlugin::$widget_version ),
        );
      ?></p>
      <p>
        <?php
        echo sprintf(
          esc_html__(
            /* translators: the placeholders are opening and closing tags for a link (<a> tag) */
            'Please rate OpenPorte on WordPress.org to help us get the word out.',
            'openporte',
          ),
          '<a href="https://wordpress.org/support/plugin/openporte/reviews/" target="_blank" rel="noopener noreferrer">',
          '</a>',
        ); ?>
      </p>
      <p>
        <?php
        echo sprintf(
          /* translators: %1$s and %2$s are the opening and closing tags of a link to the ALTCHA project. */
          esc_html__('Powered by the %1$sALTCHA%2$s proof-of-work open-source project.', 'openporte'),
          '<a href="https://github.com/altcha-org/altcha" target="_blank" rel="noopener noreferrer">',
          '</a>',
        ); ?>
      </p>
      <p>
        <a href="https://github.com/openporte/openporte" target="_blank" rel="noopener noreferrer" style="display: inline-flex; gap: 0.3rem;">
          <span><?php echo esc_html__('Star OpenPorte on GitHub!', 'openporte'); ?></span>
        </a>
      </p>
    </div>
  </div>
<?php
}

function openporte_general_section_callback()
{
  ?>
    <p><?php
      echo esc_html__('Choose the mode of operation for OpenPorte:', 'openporte');
    ?></p>
    <p><?php
      echo sprintf(
        /* translators: the placeholders are opening and closing tags for bold */
        esc_html__('%1$sSelf-hosted%2$s generates challenges via the WordPress REST API.', 'openporte'),
        '<strong>',
        '</strong>'
      );
    ?></p>
    <p><?php
      echo sprintf(
        /* translators: the placeholders are opening and closing tags for bold */
        esc_html__('%1$sCustom%2$s lets you point to your own ALTCHA-compatible backend.', 'openporte'),
        '<strong>',
        '</strong>'
      );
    ?></p>
    <p><?php
      echo esc_html__('Both modes run without any external paid service.', 'openporte');
    ?></p>
  <?php
}

// TODO: where is it used or where was it used?
function openporte_widget_section_callback()
{
  ?>

    <p><?php echo esc_html__('Customise the widget look and feel to fit your needs.', 'openporte'); ?></p>

  <?php
}

function openporte_wordpress_section_callback()
{
  ?>

    <p><?php echo esc_html__('Activate OpenPorte for the core WordPress functionalities.', 'openporte'); ?></p>
    <p><?php echo sprintf(
          /* translators: the placeholder will be replaced with the shortcode */
          esc_html__('Use %s shortcode anywhere in your HTML, Post, or Page content.', 'openporte'), '<code>[openporte]</code>',
        );
      ?></p>

  <?php
}

function openporte_integrations_section_callback()
{
  ?>

    <p><?php echo esc_html__('Activate OpenPorte for these integrations.', 'openporte'); ?></p>
    <p><?php echo sprintf(
          /* translators: the placeholder will be replaced with the shortcode */
          esc_html__('Use %s shortcode anywhere in your integrated plugins content.', 'openporte'), '<code>[openporte]</code>',
        );
      ?></p>

  <?php
}

/**
 * Renderer for text and URL input settings fields.
 * Supports the `custom` arg to add a `data-custom-api` attribute for JS-based
 * mode switching (see public/admin.js).
 * 
 * @since 1.28.0
 */
function openporte_settings_text_callback(array $args)
{
  $type = $args['type'];
  if ($type === 'url') {
    $type = 'text';
    $inputmode = 'url';
  } else {
    $inputmode = null;
  }
  $name = $args['name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $placeholder = isset($args['placeholder']) ? $args['placeholder'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $custom = isset($args['custom']) ? $args['custom'] : '';
  $description = isset($args['description']) ? $args['description'] : null;
  $tooltip = isset($args['tooltip']) ? $args['tooltip'] : '';
  $class = 'regular-text';
  $autocomplete = 'off';
  $setting = get_option($name);
  $value = isset($setting) ? esc_attr($setting) : '';
?>
  <?php if ($description) { ?>
    <div><label class="description" for="<?php echo esc_attr($name); ?>">
      <?php echo esc_html($description); ?>
    </label></div>
  <?php } ?>
  <input autocomplete="<?php echo esc_attr($autocomplete); ?>"
    <?php echo $custom === true ? ' data-custom-api' : ''; ?>
    type="<?php echo esc_attr($type); ?>"
    name="<?php echo esc_attr($name); ?>"
    id="<?php echo esc_attr($name); ?>"
    title="<?php echo esc_attr($tooltip); ?>"
    <?php echo is_null($inputmode) ? '' : ' inputmode="' . esc_attr($inputmode) . '"'; ?>
    <?php echo is_null($class) ? '' : ' class="' . esc_attr($class) . '"'; ?>
    <?php echo is_null($placeholder) ? '' : ' placeholder="' . esc_attr($placeholder) . '"'; ?>
    value="<?php echo esc_attr($value); ?>"
    <?php echo $disabled === true ? ' disabled' : ''; ?>>
  <?php if ($hint) { ?>
    <div class="openporte-hint">
      <?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?>
    </div>
  <?php } ?>
<?php
}

/**
 * Renderer for password input settings fields. Includes an optional Show/Hide
 * toggle button controlled by the `display_toggle` arg, plus optional Copy and
 * Regenerate action buttons controlled by `display_copy` / `display_regenerate`
 * (see public/admin.js for their behaviour). The action buttons are keyed on
 * class + data-target only — never DOM position — so the planned move from
 * buttons to in-field icons (#70) is a markup/CSS change with no JS impact.
 *
 * @since 1.28.0
 * @since 1.29.0 Added the `display_copy` and `display_regenerate` args.
 */
function openporte_settings_password_callback(array $args)
{
  $name = $args['name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $placeholder = isset($args['placeholder']) ? $args['placeholder'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $display_toggle = isset($args['display_toggle']) ? $args['display_toggle'] : false;
  $display_copy = isset($args['display_copy']) ? $args['display_copy'] : false;
  $display_regenerate = isset($args['display_regenerate']) ? $args['display_regenerate'] : false;
  $description = isset($args['description']) ? $args['description'] : null;
  $tooltip = isset($args['tooltip']) ? $args['tooltip'] : '';
  $class = 'openporte-large-text';
  $autocomplete = 'new-password';
  $setting = get_option($name);
  $value = isset($setting) ? esc_attr($setting) : '';
?>
  <?php if ($description) { ?>
    <div><label class="description" for="<?php echo esc_attr($name); ?>">
      <?php echo esc_html($description); ?>
    </label></div>
  <?php } ?>
  <input autocomplete="<?php echo esc_attr($autocomplete); ?>"
    type="password"
    name="<?php echo esc_attr($name); ?>"
    id="<?php echo esc_attr($name); ?>"
    class="<?php echo esc_attr($class); ?>"
    title="<?php echo esc_attr($tooltip); ?>"
    <?php echo is_null($placeholder) ? '' : ' placeholder="' . esc_attr($placeholder) . '"'; ?>
    value="<?php echo esc_attr($value); ?>"
    <?php echo $disabled === true ? ' disabled' : ''; ?>>
  <?php if ($display_toggle === true): ?>
    <button type="button" class="button button-secondary openporte-toggle-password"
      data-target="<?php echo esc_attr($name); ?>"
      data-label-show="<?php echo esc_attr__('Show', 'openporte'); ?>"
      data-label-hide="<?php echo esc_attr__('Hide', 'openporte'); ?>">
      <?php echo esc_html__('Show', 'openporte'); ?>
    </button>
  <?php endif; ?>
  <?php if ($display_copy === true): ?>
    <button type="button" class="button button-secondary openporte-copy-password"
      data-target="<?php echo esc_attr($name); ?>"
      data-label-copy="<?php echo esc_attr__('Copy', 'openporte'); ?>"
      data-label-copied="<?php echo esc_attr__('Copied!', 'openporte'); ?>"
      data-label-failed="<?php echo esc_attr__('Copy failed', 'openporte'); ?>">
      <?php echo esc_html__('Copy', 'openporte'); ?>
    </button>
  <?php endif; ?>
  <?php if ($display_regenerate === true): ?>
    <button type="button" class="button button-secondary openporte-regenerate-secret"
      data-target="<?php echo esc_attr($name); ?>">
      <?php echo esc_html__('Regenerate', 'openporte'); ?>
    </button>
  <?php endif; ?>
  <?php if ($hint) { ?>
    <div class="openporte-hint">
      <?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?>
    </div>
  <?php } ?>
<?php
}

/**
 * Renders the `input` element of a checkbox settings field.
 *
 * Shared by both renderings of openporte_settings_checkbox_callback() — plain
 * checkbox and toggle switch — so the two cannot drift apart. The toggle is a
 * CSS skin over this very element (see public/admin.css), never a `button`
 * substitute, so it keeps the native keyboard and screen-reader behaviour.
 *
 * @since 1.28.0
 *
 * @param string $name     Option name, used as both the `name` and `id` attribute.
 * @param mixed  $setting  Current option value; the box is checked when it equals 1.
 * @param bool   $disabled Whether to render the input disabled.
 */
function openporte_render_checkbox_input($name, $setting, $disabled)
{
?>
  <input autocomplete="off"
    type="checkbox"
    name="<?php echo esc_attr($name); ?>"
    id="<?php echo esc_attr($name); ?>"
    value="1"
    <?php checked(1, $setting, true); ?>
    <?php echo $disabled === true ? ' disabled' : ''; ?>>
<?php
}

/**
 * Renderer for checkbox input settings fields.
 *
 * Renders a plain checkbox by default. When the `toggle_label` arg is present
 * the same checkbox is instead wrapped in a toggle switch with the `toggle_label`
 * as the label for the toggle switch. The stored value is `1`/empty either way.
 *
 * @since 1.28.0
 *
 * @param array $args {
 *     @type string $name          Option name. Required.
 *     @type string $description   Label rendered above the control. Optional, and
 *                                 not to be combined with `toggle_label` — it
 *                                 would compete with the toggle's own label.
 *     @type string $hint          Explanatory text rendered below it. Optional.
 *     @type bool   $disabled      Render the input disabled. Optional.
 *     @type string $toggle_label  Label for the toggle switch. Optional.
 *   }
 */
function openporte_settings_checkbox_callback(array $args)
{
  $name = $args['name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $description = isset($args['description']) ? $args['description'] : null;
  $toggle_label = isset($args['toggle_label']) ? $args['toggle_label'] : null;
  $setting = get_option($name);
?>
  <?php if ($description) { ?>
    <div><label class="description" for="<?php echo esc_attr($name); ?>">
      <?php echo esc_html($description); ?>
    </label></div>
  <?php } ?>
  <?php if ($toggle_label) { ?>
    <div class="openporte-toggle">
      <?php openporte_render_checkbox_input($name, $setting, $disabled); ?>
      <label class="openporte-toggle-on" for="<?php echo esc_attr($name); ?>">
        <?php echo esc_html($toggle_label); ?>
      </label>
    </div>
  <?php } else { ?>
    <?php openporte_render_checkbox_input($name, $setting, $disabled); ?>
  <?php } ?>
  <?php if ($hint) { ?>
    <div class="openporte-hint">
      <?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?>
    </div>
  <?php } ?>
<?php
}

/**
 * Renderer for generic input settings fields: text, url, checkbox, number, password.
 * Supports the `custom` arg to add a `data-custom-api` attribute for JS-based
 * mode switching (see public/admin.js). For password type, includes an option to add
 * a Show/Hide toggle button. Also displays an optional hint below the field.
 * 
 * @since 1.26.3
 * @deprecated 1.28.0 Use the specific callback functions for each input type instead:
 *   openporte_settings_text_callback, openporte_settings_password_callback,
 *   openporte_settings_checkbox_callback, etc.
 */
function openporte_settings_field_callback(array $args)
{
  $type = $args['type']; // HTML `input` type attribute: text, url, checkbox, number, etc.
  if ($type === 'url') {
    $type = 'text'; // Use text input for URL, since WordPress sanitizes it as a URL anyway.
    $inputmode = 'url'; // So that dynamic keyboards show the URL keyboard.
  } else {
    $inputmode = null;
  }
  $name = $args['name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $placeholder = isset($args['placeholder']) ? $args['placeholder'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $custom = isset($args['custom']) ? $args['custom'] : ''; // Useful for the "Custom" mode, to add a data attribute for JS to detect it (so we can dynamically disable elements).
  $display_toggle = isset($args['display_toggle']) ? $args['display_toggle'] : false; // Whether to display a toggle button for the field (e.g., for password fields).
  $description = isset($args['description']) ? $args['description'] : null; // HTML `label` for the input field, displayed after the input.
  $classes = array(
    'password' => 'openporte-large-text',
    'text' => 'regular-text',
    );
  $class = isset($classes[$args['type']]) ? $classes[$args['type']] : null;
  $autocompletemodes = array(
    'password' => 'new-password'
    );
  $autocomplete = isset($autocompletemodes[$args['type']]) ? $autocompletemodes[$args['type']] : 'off'; // HTML `autocomplete` attribute for the input field.
  // Get the current value of the option from the database, useful for interpreting the value for checkboxes.
  $setting = get_option($name);
  $value = isset($setting) ? esc_attr($setting) : '';
  if ($type === "checkbox") {
    $value = 1;
  }
?>
  <?php if ($description) { ?>
    <div><label class="description" for="<?php echo esc_attr($name); ?>">
      <?php echo esc_html($description); ?>
    </label></div>
  <?php } ?>
  <input autocomplete="<?php echo esc_attr($autocomplete); ?>"
    <?php echo $custom === true ? ' data-custom-api' : ''; ?>
    type="<?php echo esc_attr($type); ?>"
    name="<?php echo esc_attr($name); ?>"
    id="<?php echo esc_attr($name); ?>"
    <?php echo is_null($inputmode) ? '' : ' inputmode="' . esc_attr($inputmode) . '"'; ?>
    <?php echo is_null($class) ? '' : ' class="' . esc_attr($class) . '"'; ?>
    <?php echo is_null($placeholder) ? '' : ' placeholder="' . esc_attr($placeholder) . '"'; ?>
    value="<?php echo esc_attr($value); ?>"
    <?php $type === "checkbox" ? checked(1, $setting, true) : ""; ?>
    <?php echo $disabled === true ? ' disabled' : ''; ?>>
  <?php if ($type === 'password' && $display_toggle === true): ?>
    <button type="button" class="button button-secondary openporte-toggle-password"
      data-target="<?php echo esc_attr($name); ?>"
      data-label-show="<?php echo esc_attr__('Show', 'openporte'); ?>"
      data-label-hide="<?php echo esc_attr__('Hide', 'openporte'); ?>">
      <?php echo esc_html__('Show', 'openporte'); ?>
    </button>
  <?php endif; ?>
  <?php if ($hint) { ?>
    <div class="openporte-hint">
      <?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?>
    </div>
  <?php } ?>
<?php
}

/**
 * Renderer for select dropdown settings fields. Takes an `options` arg
 * (key => label pairs) and renders a <select> with those options. Displays
 * an optional description label and hint below the field.
 *
 * Supports the `disabled_note` arg: a line explaining why the control is
 * disabled, printed with a `data-selfhosted-note` attribute so public/admin.js
 * can show/hide it live when the API Mode changes. Same markup as
 * openporte_render_preset_select().
 *
 * The `selfhosted_only` arg means exactly what it means there: the control is
 * inert in Custom API mode, so it is marked `data-selfhosted-api` and
 * public/admin.js disables it live when the mode changes. Emitting it here
 * rather than patching the attribute on in JS keeps the no-JS fallback resting
 * on the served markup alone.
 *
 * @since 1.27.0
 * @since 1.29.0 Added the `disabled_note` and `selfhosted_only` args.
 */
function openporte_settings_select_callback(array $args)
{
  $name = $args['name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $disabled_note = isset($args['disabled_note']) ? $args['disabled_note'] : null;
  $selfhosted_only = isset($args['selfhosted_only']) ? $args['selfhosted_only'] : false;
  $description = isset($args['description']) ? $args['description'] : null;
  $tooltip = isset($args['tooltip']) ? $args['tooltip'] : '';
  $options = isset($args['options']) ? $args['options'] : array();
  $setting = get_option($name);
  $value = isset($setting) ? esc_attr($setting) : '';
  // Inverse of the data-custom-api attribute used by the Challenge URL field:
  // this control is one Custom mode takes away, not one it needs.
  $mode_attr = $selfhosted_only ? ' data-selfhosted-api' : '';
?>
  <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>"<?php echo $mode_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded literal attribute name, no dynamic content. ?> <?php echo $disabled === true ? ' disabled' : ''; ?> title="<?php echo esc_attr($tooltip); ?>">
    <?php
      foreach ( $options as $opt_key => $opt_value ) {
        echo '<option value="' . esc_attr( $opt_key ) . '" '
          . selected($value, $opt_key, false )
          . '>' . esc_html($opt_value) . '</option>';
      }
    ?>
  </select>
  <?php if ($disabled_note) { ?>
    <div class="openporte-hint" data-selfhosted-note<?php echo $disabled === true ? '' : ' style="display:none"'; ?>>
      <em><?php echo esc_html($disabled_note); ?></em>
    </div>
  <?php } ?>
  <?php if ($description) { ?>
    <label class="description" for="<?php echo esc_attr($name); ?>">
      <?php echo esc_html($description); ?>
    </label>
  <?php } ?>
  <?php if ($hint) { ?>
    <div class="openporte-hint"><?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?></div>
  <?php } ?>
<?php
}

/**
 * Renderer for a preset <select> plus a "Custom" choice revealing a companion
 * number input — the shape shared by Expiration and Replay limit.
 *
 * Generic on purpose: it renders whatever presets it is handed, so another
 * setting of the same shape needs a three-line callback and no new markup (see
 * openporte_settings_expires_callback() and
 * openporte_settings_replaylimit_callback()). This is settings-page markup, not
 * a public API; the sibling renderer for plain dropdowns is
 * openporte_settings_select_callback(), and `disabled`, `disabled_note` and
 * `selfhosted_only` mean the same thing in both.
 *
 * The companion input is a plain form field, NOT a registered option: the
 * setting's sanitize callback reads it when the select submits the literal
 * string 'custom'. public/admin.js toggles its visibility with the select.
 * A stored value outside the presets therefore renders as "Custom" with the
 * number pre-filled, so a value set over WP-CLI displays honestly and no
 * migration is ever needed.
 *
 * @since 1.29.0
 * @since 1.29.0 Added the `value` arg, for a setting whose reader validates
 *               the stored option before enforcing it.
 *
 * @param array $args {
 *     @type string $name          Option name. Required.
 *     @type array  $presets       value => label pairs. Required.
 *     @type string $custom_name   Companion number input's form field. Required.
 *     @type int    $min           Minimum accepted by the number input. Required.
 *     @type int    $max           Maximum accepted by the number input. Required.
 *     @type string $hint          Explanatory text rendered below. Optional.
 *     @type bool   $disabled      Render both controls disabled. Optional.
 *     @type string $disabled_note Line explaining why, shown while disabled.
 *                                 Optional; also toggled by public/admin.js.
 *     @type bool   $selfhosted_only Mark the controls as inert in Custom API
 *                                 mode, so admin.js can disable them live.
 *     @type int    $value         Value to display, when the setting's own
 *                                 reader validates the stored option rather
 *                                 than using it verbatim. Optional; defaults
 *                                 to the stored option floored at 0.
 *   }
 */
function openporte_render_preset_select(array $args)
{
  $name = $args['name'];
  $presets = $args['presets'];
  $custom_name = $args['custom_name'];
  $hint = isset($args['hint']) ? $args['hint'] : null;
  $disabled = isset($args['disabled']) ? $args['disabled'] : false;
  $disabled_note = isset($args['disabled_note']) ? $args['disabled_note'] : null;
  $selfhosted_only = isset($args['selfhosted_only']) ? $args['selfhosted_only'] : false;
  // max(0, intval()) rather than absint(): a stored -5 is a broken value, and
  // showing it as 5 would tell the admin the opposite of what takes effect.
  // A setting whose reader validates the option further passes `value`.
  $value = isset($args['value'])
    ? intval($args['value'])
    : max(0, intval(get_option($name)));
  $is_custom = !isset($presets[$value]);
  // Inverse of the data-custom-api attribute used by the Challenge URL field:
  // these controls are the ones Custom mode takes away, not the ones it needs.
  $mode_attr = $selfhosted_only ? ' data-selfhosted-api' : '';
?>
  <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>"<?php echo $mode_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded literal attribute name, no dynamic content. ?><?php echo $disabled === true ? ' disabled' : ''; ?>>
  <?php
    foreach ( $presets as $preset_value => $preset_label ) {
      echo '<option value="' . esc_attr( $preset_value ) . '" '
        . selected($value, $preset_value, false )
        . '>' . esc_html($preset_label) . '</option>';
    }
  ?>
    <option value="custom" <?php selected($is_custom); ?>><?php echo esc_html__('Custom', 'openporte'); ?></option>
  </select>
  <input type="number" name="<?php echo esc_attr($custom_name); ?>" id="<?php echo esc_attr($custom_name); ?>" min="<?php echo esc_attr($args['min']); ?>" max="<?php echo esc_attr($args['max']); ?>" step="1" value="<?php echo esc_attr((string) $value); ?>"<?php echo $mode_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded literal attribute name, no dynamic content. ?><?php echo $is_custom ? '' : ' style="display:none"'; ?><?php echo $disabled === true ? ' disabled' : ''; ?>>
  <?php if ($disabled_note) { ?>
    <div class="openporte-hint" data-selfhosted-note<?php echo $disabled === true ? '' : ' style="display:none"'; ?>>
      <em><?php echo esc_html($disabled_note); ?></em>
    </div>
  <?php } ?>
  <?php if ($hint) { ?>
    <div class="openporte-hint"><?php echo wp_kses($hint, OpenPortePlugin::$hint_allowed_tags); ?></div>
  <?php } ?>
<?php
}

/**
 * Renderer for the Expiration setting: presets plus a Custom number input
 * accepting 0–14400 seconds, read back by openporte_sanitize_expires().
 *
 * @since 1.28.0
 * @since 1.29.0 Rendered through openporte_render_preset_select(), and
 *               disabled in Custom API mode where the backend owns the expiry.
 */
function openporte_settings_expires_callback(array $args)
{
  openporte_render_preset_select(array_merge($args, array(
    'custom_name' => 'openporte_expires_custom',
    'min' => 0,
    'max' => 14400,
    'selfhosted_only' => true,
    'presets' => array(
      300 => __('5 minutes', 'openporte'),
      1800 => __('30 minutes', 'openporte'),
      3600 => __('1 hour', 'openporte'),
    ),
  )));
}

/**
 * Renderer for the Replay limit setting: presets plus a Custom number input
 * accepting 0–100 uses, read back by openporte_sanitize_replaylimit().
 *
 * @since 1.29.0
 */
function openporte_settings_replaylimit_callback(array $args)
{
  // Show the limit that will actually be enforced. get_replaylimit() refuses a
  // stored value that is not integer-like and falls back to the default, so
  // reading the raw option here would render "Unlimited" for a stored 0.5
  // while verify() went on enforcing 5. The filter is deliberately not applied:
  // this field edits the stored setting, not the runtime override.
  $openporte_stored_limit = OpenPortePlugin::clamp_replaylimit(
    get_option(OpenPortePlugin::$option_replaylimit, null)
  );
  openporte_render_preset_select(array_merge($args, array(
    'value' => $openporte_stored_limit === null
      ? OpenPortePlugin::$replaylimit_default
      : $openporte_stored_limit,
    'custom_name' => 'openporte_replaylimit_custom',
    'min' => 0,
    'max' => 100,
    'presets' => array(
      0 => __('Unlimited (pre-1.29 behaviour)', 'openporte'),
      1 => __('Single use (strict)', 'openporte'),
      5 => __('5 uses (recommended)', 'openporte'),
      10 => __('10 uses', 'openporte'),
    ),
  )));
}
