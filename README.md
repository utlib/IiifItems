# IIIF Toolkit

Annotate. Present. Impress.

IIIF Toolkit by University of Toronto Libraries is a plugin for Omeka Classic 2.3 and up. It integrates Mirador with a built-in annotator, a manifest generator, Simple Pages shortcodes and Exhibit Builder blocks for a rich presentation experience.

## System Requirements

* Omeka Classic 2.3 and up
* IIIF image server pointing to the Omeka installation's files/original directory (optional if you will only be importing content from existing manifests)

## Optional Requirements

IIIF Toolkit can integrate with several popular Omeka rich presentation plugins. At the moment, the following are officially supported:

* Exhibits Builder 3.x and up
* Simple Pages 3.x and up
* Neatline 2.2.x and up

## Installation

* Clone this repository to the ```plugins``` directory of your Omeka installation.
* Sign in as a super user.
* In the top menu bar, select "Plugins".
* Find IIIF Toolkit in the list of plugins and select "Install".
* If you plan to serve your own images via IIIF, see "Pointing a IIIF image server" for details.
* Select "Save Changes" to continue.

### Pointing a IIIF image server

If you plan to serve your own images via IIIF, you must install a IIIF image server pointed to the Omeka installation's ```files/original``` directory. Please consult the documentation for your preferred IIIF image server for setup instructions.

After setting up your IIIF image server, you can set the correspondence between file names in ```files/original``` and the image server's URIs.

* Sign in as a super user.
* In the top menu bar, select "Plugins".
* Find IIIF Toolkit in the list of plugins and select "Configure".
* Enter the server, prefix and identifier portion of a standard IIIF image request URI to your IIIF image server in the "IIIF Prefix" option. The following placeholders can be used to represent file-specific properties in the identifier portion:
    * ```{FULLNAME}```: The full name of the file (e.g. ```8997b027303b523ab7c4351c4761e4a0.jpg```)
    * ```{FILENAME}```: The name of the file without the extension (e.g. ```8997b027303b523ab7c4351c4761e4a0```)
    * ```{EXTENSION}```: The extension of the file, without the leading dot (e.g. ```jpg```)

For example, if your IIIF image server would serve an image as ```http://iiif.example.org/images/8997b027303b523ab7c4351c4761e4a0/full/full/0/default.jpg```, you can enter ```http://iiif.example.org/images/{FILENAME}``` for IIIF Prefix.


## Upgrading

Note: It is strongly recommended that you back up or snapshot the Omeka installation before upgrading, in case of unwanted changes or errors.

* Run ```git pull``` or replace the directory with a download.
* Sign in as a super user.
* In the top menu bar, select "Plugins".
* Find IIIF Toolkit in the list of plugins and select "Upgrade".

## License

IIIF Toolkit is licensed under Apache License 2.0.