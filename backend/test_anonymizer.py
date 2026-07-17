from app.services.anonymizer import anonymize_cv_text

sample_cv = """
İsim: Yiğit Dede
Email: yigit.dede@mail.com
Telefon: 0555 123 45 67
Kurum: Yıldız Teknik Üniversitesi
Adres: Beşiktaş, İstanbul
"""

masked_cv = anonymize_cv_text(sample_cv)
print("--- Maskelenmiş CV ---")
print(masked_cv)