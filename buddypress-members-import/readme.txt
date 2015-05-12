Features:
- Tested with WordPress 4.0 and BuddyPress 2.1.1
- WordPress/BuddyPress members can be import.
- WordPress users extra/custom fields can be mapped with the account.
- BuddyPress members extra fields(xProfile) can be mapped with the account.
- All type(checkbox, radio, select, multiselect) xProfile fields can be import.
- Existing users account can be update.
- User password can be set from CSV file.
- Custom Email template.
- Different password to different user.
- xProfile fields default visibility maintain.
- Mapping of members and groups can be done.
- Members AVATAR can be upload. Avatar will be resize to BP default avatar size.
- 2 sample CSV file present to help you to create CSV file.
- Can import data other than English.

Learn More:
Please visit: http://www.youngtechleads.com/buddypress-members-import

Before import user follow the below steps:
- Check whether the `assets` directory present in your site/project root directory or not.
- If present then, provide this directory to write permission.
- If not present then, create the same and provide this directory to write permission.

Important Note for Multivalued data:
- Say you have a field "Looking For" and this field is multivalued field(checkbox or select)
- Your options are Man, Woman, Both, Other
- Now for a user want to import Woman then in CSV file under "Looking For" column you have to write "Woman::"
- If for a user want to import Woman and Man then in CSV file under "Looking For" column you have to write "Woman::Man"
- If for a user want to import Woman, Man and both then in CSV file under "Looking For" column you have to write "Woman::Man::Both"

Multivalued field for exiting users:
If you are going to update an existing user data but don't want to update that user's a data which is multivalued field 
then you have to remove that field from the CSV file.

Change Log:
== 3.5 ==
- If image/avatar url has space then getting fatal error. Fixed.
- If any image/avatar url is 404 then fatal error coming and stop working. Handled.