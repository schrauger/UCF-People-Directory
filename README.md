# UCF-People-Directory
People directory block for the UCF College Theme.

This block prints out a directory of all people, or of specific people group categories.

## Features
* Search for people by name
* Sub-directories by selecting specific people groups to be shown
* Weighted profiles
** A person can be sorted at the top of their department listing by specifying sorting options in the person's post. A person can have multiple departments with multiple weights, so that if they're a supervisor for one department but a standard member of another, they can be weighted differently when a user views one department, but then sorted alphabetically with everyone else when the user views another department.
** Multiple weight values can be used, so that two or three equal weighted groups can be separately weighted in a directory listing before the rest of the group members are shown (ex show dean first, then supervisors, then everyone else when viewing the Enterprise department)
* Options to show and hide various elements
** Search bar
** Side filter which contains people groups
** Contact cards - useful if you want a main directory of everyone, but don't want to show contact cards and 1000 pagination links initially, only showing the contact cards after the user filters to a specific people group, or searches the directory.

## Requirements
Requires the UCF [Colleges Theme](https://github.com/UCF/Colleges-Theme), and the [UCF People CPT](https://github.com/UCF/UCF-People-CPT) plugin.
Requires [ACF Pro](https://www.advancedcustomfields.com/pro/)
